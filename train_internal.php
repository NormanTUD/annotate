<?php
/**
 * train_internal.php — Local YOLO training with robust live streaming output.
 *
 * Key improvements:
 * - Uses `script` (pty) + `stdbuf` + PYTHONUNBUFFERED for guaranteed streaming
 * - Suppresses Rich/ANSI terminal rewriting via TERM=dumb, NO_COLOR=1
 * - Monkey-patches Rich in the training script so output is plain line-by-line
 * - Structured for testability: core logic in functions, minimal top-level code
 */

// ============================================================
// CONFIGURATION
// ============================================================

define('TRAINING_TIMEOUT_SECONDS', 600);
define('PROCESS_READ_CHUNK_SIZE', 8192);
define('PROCESS_POLL_INTERVAL_US', 50000); // 50ms
define('BROWSER_FLUSH_PADDING_BYTES', 4096);
define('MIN_PRETRAINED_MODEL_SIZE', 100000); // bytes
define('PRETRAINED_DOWNLOAD_BASE_URL', 'https://github.com/ultralytics/assets/releases/download/v8.4.0/');

// ============================================================
// BOOTSTRAP (only when not in test mode)
// ============================================================

if (!defined('TRAIN_INTERNAL_TEST_MODE')) {
    bootstrap_training();
}

// ============================================================
// MAIN ENTRY POINT
// ============================================================

function bootstrap_training(): void {
    if (!file_exists("allow_local_training")) {
        print("Local training not enabled");
        exit(0);
    }

    configure_php_for_streaming();
    send_streaming_headers();
    send_browser_flush_padding();

    include_once("functions.php");
    include_once("export_helper.php");

    ob_implicit_flush(true);

    run_training_pipeline($_POST, $_SERVER['REQUEST_METHOD']);
}

// ============================================================
// PHP / HTTP STREAMING CONFIGURATION
// ============================================================

function configure_php_for_streaming(): void {
    ini_set('output_buffering', '0');
    ini_set('implicit_flush', '1');
    ini_set('memory_limit', '-1');
    ini_set('max_execution_time', '0');
    set_time_limit(0);
    ini_set('zlib.output_compression', 'Off');

    // Kill any existing output buffers
    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    if (function_exists('apache_setenv')) {
        apache_setenv('no-gzip', '1');
    }
}

function send_streaming_headers(): void {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('X-Accel-Buffering: no');
    header('Content-Encoding: none');
    // DO NOT set Transfer-Encoding: chunked manually.
    // PHP/webserver handles this automatically.
    // Manually setting it without proper chunk framing causes NetworkError.
}

function send_browser_flush_padding(): void {
    echo str_repeat(" ", BROWSER_FLUSH_PADDING_BYTES) . "\n";
    flush();
}

// ============================================================
// OUTPUT HELPERS
// ============================================================

/**
 * Strip ANSI escape codes and terminal rewriting sequences from text.
 * Handles Rich library output, progress bars, cursor movement, etc.
 */
function strip_ansi(string $text): string {
    // Remove ESC[K (erase to end of line)
    $text = preg_replace('/\x1b\[K/', '', $text);

    // Remove cursor movement sequences (ESC[nA = up, ESC[nB = down, etc.)
    $text = preg_replace('/\x1b\[\d*[ABCDEFGHJKSTfn]/', '', $text);

    // Remove cursor save/restore
    $text = preg_replace('/\x1b\[su/', '', $text);
    $text = preg_replace('/\x1b[78]/', '', $text);

    // Remove carriage return based overwrites (progress bar redraws)
    // Keep only the last segment after \r on each line
    $text = preg_replace('/^.*\r(?!\n)/m', '', $text);

    // Remove all remaining ANSI escape sequences (ESC[ ... letter)
    $text = preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $text);

    // Remove orphaned bracket codes where ESC char was lost in transit
    $text = preg_replace('/\[([0-9;]*)[mKHJGsu]/', '', $text);

    // Remove OSC sequences (ESC] ... BEL/ST)
    $text = preg_replace('/\x1b\].*?(\x07|\x1b\\\\)/', '', $text);

    // Collapse multiple blank lines into one
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    return $text;
}

/**
 * Output text with ANSI codes stripped, immediately flushed.
 */
function output(string $text): void {
    $cleaned = strip_ansi($text);
    if ($cleaned === '') {
        return;
    }
    echo $cleaned;
    flush();
}

// ============================================================
// TRAINING PIPELINE
// ============================================================

function run_training_pipeline(array $post_data, string $request_method): void {
    // --- Validate request ---
    if ($request_method !== 'POST') {
        output("❌ Error: Use the form button to start training.\n");
        exit;
    }

    $params = validate_and_extract_params($post_data);
    if ($params === null) {
        exit;
    }

    output("✅ Parameters: model={$params['model_to_load']}, epochs={$params['epochs']}, name={$params['model_name']}, mode={$params['training_mode']}\n");
    if ($params['fine_tune']) {
        output("   ℹ️  Fine-tuning from pretrained COCO weights (80 everyday object classes)\n");
    }

    // --- Step 1: Generate the export ---
    output("\n📦 Step 1: Generating training data...\n");
    $dataset = prepare_dataset($params);
    if ($dataset === null) {
        exit;
    }

    // --- Step 2: Extract images from DB ---
    output("\n🖼️  Step 2: Extracting images from database...\n");
    $img_count = extract_images_to_disk($dataset['images'], $dataset['tmp_dir']);
    output("   ✅ Extracted $img_count images total.\n");

    // --- Step 3: Run YOLO training ---
    output("\n🏃 Step 3: Starting YOLO training ({$params['epochs']} epochs, model: {$params['model_to_load']}, mode: {$params['training_mode']})...\n");

    $training_success = run_yolo_training($params, $dataset);
    if (!$training_success) {
        output("Training failed.\n");
        exit;
    }

    output("\n✅ Training completed successfully!\n");

    // --- Step 4: Find best.pt and upload model ---
    output("\n💾 Step 4: Uploading trained model...\n");
    $upload_success = find_and_upload_model($params['model_name'], $dataset['tmp_dir']);
    if (!$upload_success) {
        exit;
    }

    // --- Cleanup ---
    output("\n🧹 Cleaning up temporary files...\n");
    system("rm -rf " . escapeshellarg($dataset['tmp_dir']));
    output("   Done.\n");

    output("\n🎉 All done! Model '{$params['model_name']}' is now available in the models list.\n");
}

// ============================================================
// PARAMETER VALIDATION
// ============================================================

function validate_and_extract_params(array $post_data): ?array {
    $epochs = intval($post_data['epochs'] ?? 50);
    $model_yaml = $post_data['model'] ?? 'yolo11s.yaml';
    $model_name = trim($post_data['model_name'] ?? 'auto_trained');
    $fine_tune = isset($post_data['fine_tune']) && $post_data['fine_tune'] == '1';

    // Determine the model to load
    if ($fine_tune) {
        $pt_filename = preg_replace('/\.yaml$/', '.pt', $model_yaml);
        $model_to_load = "/tmp/$pt_filename";
        $training_mode = 'fine-tune';

        if (!ensure_pretrained_model($model_to_load, $pt_filename)) {
            return null;
        }
    } else {
        $model_to_load = $model_yaml;
        $training_mode = 'from-scratch';
    }

    if (empty($model_name) || strtolower($model_name) === 'none') {
        output("❌ Error: Please provide a valid model name.\n");
        return null;
    }

    if (!get_number_of_annotated_imgs()) {
        output("❌ Error: No annotated images available for training.\n");
        return null;
    }

    return [
        'epochs' => $epochs,
        'model_yaml' => $model_yaml,
        'model_name' => $model_name,
        'model_to_load' => $model_to_load,
        'fine_tune' => $fine_tune,
        'training_mode' => $training_mode,
    ];
}

function ensure_pretrained_model(string $model_path, string $pt_filename): bool {
    if (file_exists($model_path) && filesize($model_path) >= MIN_PRETRAINED_MODEL_SIZE) {
        output("   ✅ Pretrained model already cached at $model_path (" . round(filesize($model_path) / 1024 / 1024, 2) . " MB)\n");
        return true;
    }

    output("   ⬇️  Downloading pretrained model ($pt_filename) to $model_path...\n");
    $download_url = PRETRAINED_DOWNLOAD_BASE_URL . $pt_filename;
    $dl_cmd = "curl -L -o " . escapeshellarg($model_path) . " " . escapeshellarg($download_url) . " 2>&1";
    $dl_output = shell_exec($dl_cmd);

    if (!file_exists($model_path) || filesize($model_path) < MIN_PRETRAINED_MODEL_SIZE) {
        output("   ❌ Failed to download pretrained model.\n");
        output("   $dl_output\n");
        output("   You can manually download it and place it at $model_path\n");
        return false;
    }

    output("   ✅ Downloaded pretrained model (" . round(filesize($model_path) / 1024 / 1024, 2) . " MB)\n");
    return true;
}

// ============================================================
// DATASET PREPARATION
// ============================================================

function prepare_dataset(array $params): ?array {
    $_GET['epochs'] = $params['epochs'];
    $_GET['model'] = $params['model_to_load'];
    $_GET['validation_split'] = 0;
    $_GET['max_files'] = 0;
    $_GET['empty'] = 0;
    $_GET['group_by_perception_hash'] = 0;
    $_GET['only_curated'] = 0;
    $_GET['only_uncurated'] = 0;
    $_GET['max_truncation'] = 100;

    $images_and_data = get_annotated_images();
    $images = $images_and_data[0];
    $categories = $images_and_data[3];

    if (empty($images)) {
        output("❌ Error: No annotated images found.\n");
        return null;
    }

    output("   Found " . count($images) . " annotated images.\n");

    $tmp_dir = create_tmp_dir();
    output("   Working directory: $tmp_dir\n");

    // Build category mapping
    $category_numbers = [];
    $_labels = [];

    $cat_query = "SELECT id - 1, name FROM category ORDER BY id ASC";
    $cat_res = rquery($cat_query);
    while ($row = mysqli_fetch_row($cat_res)) {
        $db_id = intval($row[0]);
        $cat_name = strtolower($row[1]);
        $category_numbers[$cat_name] = $db_id;
    }

    // Build dataset.yaml
    $dataset_yaml = "path: $tmp_dir\n";
    $dataset_yaml .= "train: images/\n";
    $dataset_yaml .= "val: images/\n";
    $dataset_yaml .= "names:\n";

    foreach ($category_numbers as $cat => $db_id) {
        $dataset_yaml .= "  $db_id: $cat\n";
        $_labels[$db_id] = $cat;
    }

    mkdir("$tmp_dir/labels/", 0777, true);
    mkdir("$tmp_dir/images/", 0777, true);

    file_put_contents("$tmp_dir/dataset.yaml", $dataset_yaml);
    file_put_contents("$tmp_dir/labels.json", json_encode($_labels));

    output("   Created dataset.yaml with " . count($category_numbers) . " categories.\n");

    // Write label files
    foreach ($images as $fn => $img) {
        $fn_txt = preg_replace("/\.\w+$/", ".txt", $fn);
        $str_arr = [];
        foreach ($img as $single_anno) {
            $category_number = $category_numbers[$single_anno["category"]];
            $x_center = $single_anno["x_center"];
            $y_center = $single_anno["y_center"];
            $width_relative = $single_anno["w_rel"];
            $height_relative = $single_anno["h_rel"];
            $str_arr[] = "$category_number $x_center $y_center $width_relative $height_relative";
        }
        file_put_contents("$tmp_dir/labels/$fn_txt", join("\n", array_unique($str_arr)) . "\n");
    }

    output("   Written " . count($images) . " label files.\n");

    return [
        'images' => $images,
        'categories' => $categories,
        'category_numbers' => $category_numbers,
        'tmp_dir' => $tmp_dir,
    ];
}

// ============================================================
// IMAGE EXTRACTION
// ============================================================

function extract_images_to_disk(array $images, string $tmp_dir): int {
    $img_count = 0;
    foreach ($images as $fn => $img) {
        $query = "SELECT image_content FROM image_data WHERE filename = " . esc($fn) . " LIMIT 1";
        $res = rquery($query);
        $row = mysqli_fetch_row($res);
        if ($row && $row[0]) {
            file_put_contents("$tmp_dir/images/$fn", $row[0]);
            $img_count++;
            if ($img_count % 50 == 0) {
                output("   Extracted $img_count images...\n");
            }
        }
    }
    return $img_count;
}

// ============================================================
// YOLO TRAINING EXECUTION
// ============================================================

/**
 * Check that ultralytics is importable.
 */
function verify_ultralytics(): bool {
    $check_output = [];
    exec("python3 -c \"from ultralytics import YOLO; print('ok')\" 2>&1", $check_output, $check_exit);
    if ($check_exit !== 0) {
        output("   ❌ ultralytics not importable: " . implode("\n", $check_output) . "\n");
        output("Training aborted.\n");
        return false;
    }
    output("   ✅ ultralytics available.\n");
    return true;
}

/**
 * Generate the Python training script content.
 * Rich is monkey-patched to prevent terminal rewriting.
 */
function generate_training_script(array $params, string $tmp_dir): string {
    $model_to_load = $params['model_to_load'];
    $training_mode = $params['training_mode'];
    $epochs = $params['epochs'];
    $imgsz = $GLOBALS["imgsz"] ?? 800;

    return <<<PYTHON
import os, sys

# === Force unbuffered, plain output regardless of Rich/YOLO internals ===
sys.stdout.reconfigure(line_buffering=True)
sys.stderr.reconfigure(line_buffering=True)

# Suppress Rich and fancy terminal output at the environment level
os.environ["TERM"] = "dumb"
os.environ["NO_COLOR"] = "1"
os.environ["COLUMNS"] = "300"
os.environ["PYTHONUNBUFFERED"] = "1"
os.environ["MPLCONFIGDIR"] = "/tmp/matplotlib_config"
os.environ["YOLO_CONFIG_DIR"] = "/tmp/Ultralytics"
os.environ["HOME"] = "/tmp"

os.makedirs("/tmp/matplotlib_config", exist_ok=True)

# === Monkey-patch Rich before anything imports it ===
# This ensures that no matter what Ultralytics or its deps do with Rich,
# we get plain, flushed, line-by-line output.
try:
    import rich.console
    import rich.progress
    import rich.status

    class _PlainConsole(rich.console.Console):
        def __init__(self, *args, **kwargs):
            kwargs["no_color"] = True
            kwargs["highlight"] = False
            kwargs["force_terminal"] = False
            kwargs["force_jupyter"] = False
            super().__init__(*args, **kwargs)

        def print(self, *args, **kwargs):
            import builtins
            parts = []
            for a in args:
                parts.append(str(a))
            builtins.print(" ".join(parts), flush=True)

        def log(self, *args, **kwargs):
            self.print(*args)

        def status(self, *args, **kwargs):
            class _FakeStatus:
                def __enter__(self): return self
                def __exit__(self, *a): pass
                def update(self, *a): pass
                def start(self): pass
                def stop(self): pass
            return _FakeStatus()

    # Patch the module-level Console class
    rich.console.Console = _PlainConsole

    # Disable Rich progress bars entirely
    class _FakeProgress:
        def __init__(self, *args, **kwargs): pass
        def __enter__(self): return self
        def __exit__(self, *a): pass
        def add_task(self, *a, **kw): return 0
        def update(self, *a, **kw): pass
        def start(self): pass
        def stop(self): pass

    rich.progress.Progress = _FakeProgress

except ImportError:
    pass  # Rich not installed, no patching needed

# === Now import ultralytics (which imports Rich internally) ===
from ultralytics import YOLO

print("Loading model: $model_to_load (mode: $training_mode)", flush=True)
model = YOLO("$model_to_load")

print("Image size: $imgsz", flush=True)
print("Starting training...", flush=True)

results = model.train(
    data="$tmp_dir/dataset.yaml",
    epochs=$epochs,
    batch=4,
    imgsz=$imgsz,
    project="$tmp_dir/runs",
    name="train",
    device="cpu",
    workers=2,
    verbose=True,
    lr0=0.01,
    lrf=0.01,
    warmup_epochs=0,
    augment=False,
    hsv_h=0.0,
    hsv_s=0.0,
    hsv_v=0.0,
    degrees=0.0,
    translate=0.0,
    scale=0.0,
    fliplr=0.0,
    flipud=0.0,
    mosaic=0.0,
    mixup=0.0,
    copy_paste=0.0,
    conf=0.1,
    freeze=None,
    cos_lr=False,
    patience=0,
    val=False
)

print("TRAINING_COMPLETE", flush=True)
PYTHON;
}

/**
 * Build the shell command that wraps the training script with pty + stdbuf.
 * This ensures live output regardless of what Rich/YOLO/Python does internally.
 */
function build_training_command(string $train_script_path): string {
    $env_vars = implode(' ', [
        'PYTHONUNBUFFERED=1',
        'TERM=dumb',
        'NO_COLOR=1',
        'COLUMNS=300',
        'MPLCONFIGDIR=/tmp/matplotlib_config',
        'HOME=/tmp',
        'YOLO_CONFIG_DIR=/tmp/Ultralytics',
    ]);

    $python_cmd = "env $env_vars python3 " . escapeshellarg($train_script_path);

    // Try to use `script` for pty emulation (most robust for streaming)
    // Falls back to stdbuf if script is not available
    if (command_exists('script')) {
        // script -qefc wraps in a pty so Rich thinks it's a terminal
        // Combined with TERM=dumb and NO_COLOR, it outputs plain text eagerly
        $inner_cmd = "stdbuf -oL -eL $python_cmd";
        $cmd = "script -qefc " . escapeshellarg($inner_cmd) . " /dev/null 2>&1";
    } elseif (command_exists('stdbuf')) {
        // stdbuf forces line-buffering at the libc level
        $cmd = "stdbuf -oL -eL $python_cmd 2>&1";
    } else {
        // Fallback: just env vars and hope for the best
        $cmd = "$python_cmd 2>&1";
    }

    return $cmd;
}

/**
 * Check if a command exists on the system.
 */
function command_exists(string $command): bool {
    $result = shell_exec("which " . escapeshellarg($command) . " 2>/dev/null");
    return !empty(trim($result ?? ''));
}

/**
 * Execute the YOLO training process with live streaming output.
 */
function run_yolo_training(array $params, array $dataset): bool {
    if (!verify_ultralytics()) {
        return false;
    }

    $tmp_dir = $dataset['tmp_dir'];
    $imgsz = $GLOBALS["imgsz"] ?? 800;

    output("   Image size: $imgsz\n");

    // Generate and write training script
    $train_script = generate_training_script($params, $tmp_dir);
    $train_script_path = "$tmp_dir/train.py";
    file_put_contents($train_script_path, $train_script);

    // Build the wrapped command
    $train_cmd = build_training_command($train_script_path);
    output("   Command: $train_cmd\n\n");

    // Execute with streaming
    return execute_streaming_process($train_cmd);
}

/**
 * Run a command and stream its output in real-time.
 * Returns true if the process exits with code 0.
 */
function execute_streaming_process(string $command): bool {
    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"],  // stderr
    ];

    $process = proc_open($command, $descriptorspec, $pipes);

    if (!is_resource($process)) {
        output("❌ Error: Could not start training process.\n");
        return false;
    }

    fclose($pipes[0]); // Close stdin

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $last_output_time = time();

    while (true) {
        $status = proc_get_status($process);

        $stdout_chunk = fread($pipes[1], PROCESS_READ_CHUNK_SIZE);
        $stderr_chunk = fread($pipes[2], PROCESS_READ_CHUNK_SIZE);

        if ($stdout_chunk !== false && $stdout_chunk !== "") {
            output($stdout_chunk);
            $last_output_time = time();
        }

        if ($stderr_chunk !== false && $stderr_chunk !== "") {
            output($stderr_chunk);
            $last_output_time = time();
        }

        if (!$status['running']) {
            // Drain remaining output
            do {
                $r1 = fread($pipes[1], PROCESS_READ_CHUNK_SIZE);
                $r2 = fread($pipes[2], PROCESS_READ_CHUNK_SIZE);
                if ($r1) output($r1);
                if ($r2) output($r2);
            } while ($r1 || $r2);
            flush();
            break;
        }

        if ((time() - $last_output_time) > TRAINING_TIMEOUT_SECONDS) {
            output("\n⚠️ No output for " . TRAINING_TIMEOUT_SECONDS . "s, killing process...\n");
            proc_terminate($process);
            break;
        }

        usleep(PROCESS_POLL_INTERVAL_US);
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);

    if ($exit_code !== 0) {
        output("\n❌ Training failed with exit code $exit_code\n");
        return false;
    }

    return true;
}

// ============================================================
// MODEL UPLOAD
// ============================================================

function find_and_upload_model(string $model_name, string $tmp_dir): bool {
    $best_pt = find_best_pt($tmp_dir);

    if (!$best_pt) {
        output("   ❌ Could not find best.pt in training output.\n");
        system("find " . escapeshellarg("$tmp_dir/runs") . " -type f 2>&1");
        output("\nTraining output missing.\n");
        return false;
    }

    output("   Found model: $best_pt (" . round(filesize($best_pt) / 1024 / 1024, 2) . " MB)\n");

    try {
        $files_array = [$best_pt];
        $pt_file_path = $best_pt;
        $pt_file = "best.pt";

        output("   Converting to TFJS and inserting into database...\n");

        insert_model_into_db($model_name, $files_array, $pt_file_path, $pt_file, false);

        output("\n✅ Model '$model_name' successfully trained and uploaded!\n");
        return true;
    } catch (\Throwable $e) {
        output("\n❌ Error uploading model: " . $e->getMessage() . "\n");
        return false;
    }
}

/**
 * Search for best.pt in known locations, then fall back to `find`.
 */
function find_best_pt(string $tmp_dir): ?string {
    $search_paths = [
        "$tmp_dir/runs/train/weights/best.pt",
        "$tmp_dir/runs/detect/train/weights/best.pt",
    ];

    foreach ($search_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    // Fallback: use find command
    $find_result = trim(shell_exec("find " . escapeshellarg($tmp_dir) . " -name 'best.pt' -type f 2>/dev/null | head -1") ?? '');
    if ($find_result && file_exists($find_result)) {
        return $find_result;
    }

    return null;
}
?>
