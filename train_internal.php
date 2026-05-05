<?php
if(!file_exists("allow_local_training")) {
	print("Local training not enabled");
	exit(0);
}

ini_set('output_buffering', 0);
ini_set('implicit_flush', 1);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '0');
set_time_limit(0);
ini_set('output_buffering', 'Off');
ini_set('zlib.output_compression', 'Off');

// Kill any existing output buffers
while (ob_get_level() > 0) {
    ob_end_flush();
}

if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}

// Send ALL headers BEFORE any output
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('X-Accel-Buffering: no');
header('Content-Encoding: none');

// HTML preamble with terminal styling
echo "<!DOCTYPE html><html><head><style>
body { background: #1e1e1e; color: #d4d4d4; font-family: 'Courier New', monospace; font-size: 14px; padding: 20px; margin: 0; }
pre { white-space: pre-wrap; word-wrap: break-word; margin: 0; line-height: 1.5; }
.ansi-bold { font-weight: bold; }
.ansi-blue { color: #569cd6; }
.ansi-red { color: #f44747; }
.ansi-green { color: #6a9955; }
.ansi-yellow { color: #dcdcaa; }
.ansi-cyan { color: #4ec9b0; }
.ansi-magenta { color: #c586c0; }
.ansi-white { color: #ffffff; }
.stderr { color: #f44747; }
</style></head><body><pre>";

// Larger padding to force browser to start rendering
echo str_repeat(" ", 4096) . "\n";
flush();

// Include AFTER output has started (no more headers after this point)
include_once("functions.php");
include_once("export_helper.php");

ob_implicit_flush(true);

/**
 * Convert ANSI escape codes to HTML spans
 */
function ansi_to_html($text, $is_stderr = false) {
    // Remove carriage return based overwrites (progress bar redraws)
    // Keep the last segment after \r on each line
    $text = preg_replace('/^.*\r(?!\n)/m', '', $text);

    // Remove ESC[K (erase to end of line)
    $text = preg_replace('/\x1b\[K/', '', $text);
    // Also handle cases where \x1b is already stripped but [K remains at line start
    $text = preg_replace('/^\[K/m', '', $text);

    // HTML-escape
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // Map ANSI codes to CSS classes
    $map = [
        '1'  => 'ansi-bold',
        '30' => 'ansi-black',
        '31' => 'ansi-red',
        '32' => 'ansi-green',
        '33' => 'ansi-yellow',
        '34' => 'ansi-blue',
        '35' => 'ansi-magenta',
        '36' => 'ansi-cyan',
        '37' => 'ansi-white',
    ];

    // Track open spans to properly close them
    $text = preg_replace_callback('/\x1b\[([0-9;]*)m/', function($m) use ($map) {
        $codes = explode(';', $m[1]);
        $result = '';
        foreach ($codes as $code) {
            $code = trim($code);
            if ($code === '0' || $code === '') {
                $result .= '</span>';
            } else {
                $class = $map[$code] ?? '';
                if ($class) {
                    $result .= "<span class=\"$class\">";
                }
            }
        }
        return $result;
    }, $text);

    // Handle the case where escape char was already consumed but bracket codes remain
    // e.g. [34m[1m patterns without the ESC prefix
    $text = preg_replace_callback('/\[([0-9;]+)m/', function($m) use ($map) {
        $codes = explode(';', $m[1]);
        $result = '';
        foreach ($codes as $code) {
            $code = trim($code);
            if ($code === '0' || $code === '') {
                $result .= '</span>';
            } else {
                $class = $map[$code] ?? '';
                if ($class) {
                    $result .= "<span class=\"$class\">";
                }
            }
        }
        return $result;
    }, $text);

    // Remove any remaining raw escape sequences
    $text = preg_replace('/\x1b\[[^A-Za-z]*[A-Za-z]/', '', $text);

    // Wrap stderr in a class
    if ($is_stderr) {
        $text = "<span class=\"stderr\">$text</span>";
    }

    return $text;
}

/**
 * Output a line with ANSI conversion
 */
function output($text, $is_stderr = false) {
    echo ansi_to_html($text, $is_stderr);
    flush();
}

// --- Validate ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    output("❌ Error: Use the form button to start training.\n");
    echo "</pre></body></html>";
    exit;
}

$epochs = intval($_POST['epochs'] ?? 50);
$model_yaml = $_POST['model'] ?? 'yolo11s.yaml';
$model_name = trim($_POST['model_name'] ?? 'auto_trained');

if (empty($model_name) || strtolower($model_name) === 'none') {
    output("❌ Error: Please provide a valid model name.\n");
    echo "</pre></body></html>";
    exit;
}

if (!get_number_of_annotated_imgs()) {
    output("❌ Error: No annotated images available for training.\n");
    echo "</pre></body></html>";
    exit;
}

output("✅ Parameters: model=$model_yaml, epochs=$epochs, name=$model_name\n");

// --- Step 1: Generate the export ---
output("\n📦 Step 1: Generating training data...\n");

$_GET['epochs'] = $epochs;
$_GET['model'] = $model_yaml;
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
    echo "</pre></body></html>";
    exit;
}

output("   Found " . count($images) . " annotated images.\n");

$tmp_dir = create_tmp_dir();
output("   Working directory: $tmp_dir\n");

// Build dataset.yaml
$dataset_yaml = "path: $tmp_dir\n";
$dataset_yaml .= "train: images/\n";
$dataset_yaml .= "val: images/\n";
$dataset_yaml .= "names:\n";

$category_numbers = array();
$_labels = [];

$cat_query = "SELECT id - 1, name FROM category ORDER BY id ASC";
$cat_res = rquery($cat_query);
while ($row = mysqli_fetch_row($cat_res)) {
    $db_id = intval($row[0]);
    $cat_name = strtolower($row[1]);
    $category_numbers[$cat_name] = $db_id;
}

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
    $str_arr = array();
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

// --- Step 2: Extract images from DB ---
output("\n🖼️  Step 2: Extracting images from database...\n");

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

output("   ✅ Extracted $img_count images total.\n");

// --- Step 3: Run YOLO training ---
output("\n🏃️ Step 3: Starting YOLO training ($epochs epochs, model: $model_yaml)...\n");

$check_output = [];
exec("python3 -c \"from ultralytics import YOLO; print('ok')\" 2>&1", $check_output, $check_exit);
if ($check_exit !== 0) {
    output("   ❌ ultralytics not importable: " . implode("\n", $check_output) . "\n");
    output("Training aborted.\n");
    echo "</pre></body></html>";
    exit;
}
output("   ✅ ultralytics available.\n");

$imgsz = $GLOBALS["imgsz"] ?? 400;

$train_script = <<<PYTHON
import os, sys
sys.stdout.reconfigure(line_buffering=True)
sys.stderr.reconfigure(line_buffering=True)
os.environ["YOLO_CONFIG_DIR"] = "/tmp/Ultralytics"

from ultralytics import YOLO

print("Loading model: $model_yaml", flush=True)
model = YOLO("$model_yaml")

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
    verbose=True
)
print("TRAINING_COMPLETE", flush=True)
PYTHON;

$train_script_path = "$tmp_dir/train.py";
file_put_contents($train_script_path, $train_script);

$train_cmd = "PYTHONUNBUFFERED=1 python3 " . escapeshellarg($train_script_path) . " 2>&1";
output("   Command: $train_cmd\n\n");

$descriptorspec = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];

$process = proc_open($train_cmd, $descriptorspec, $pipes);

if (!is_resource($process)) {
    output("❌ Error: Could not start training process.\n");
    echo "</pre></body></html>";
    exit;
}

fclose($pipes[0]);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

$last_output_time = time();
$timeout = 600;

while (true) {
    $status = proc_get_status($process);

    // Use fread instead of fgets to catch \r progress bars
    $stdout_chunk = fread($pipes[1], 8192);
    $stderr_chunk = fread($pipes[2], 8192);

    if ($stdout_chunk !== false && $stdout_chunk !== "") {
        output($stdout_chunk, false);
        $last_output_time = time();
    }

    if ($stderr_chunk !== false && $stderr_chunk !== "") {
        output($stderr_chunk, true);
        $last_output_time = time();
    }

    if (!$status['running']) {
        // Drain remaining output
        do {
            $r1 = fread($pipes[1], 8192);
            $r2 = fread($pipes[2], 8192);
            if ($r1) output($r1, false);
            if ($r2) output($r2, true);
        } while ($r1 || $r2);
        flush();
        break;
    }

    if ((time() - $last_output_time) > $timeout) {
        output("\n⚠️ No output for {$timeout}s, killing process...\n");
        proc_terminate($process);
        break;
    }

    // Shorter sleep = more responsive (50ms instead of 100ms)
    usleep(50000);
}

fclose($pipes[1]);
fclose($pipes[2]);
$exit_code = proc_close($process);

if ($exit_code !== 0) {
    output("\n❌ Training failed with exit code $exit_code\n");
    output("   Temporary files preserved at: $tmp_dir\n");
    output("Training failed.\n");
    echo "</pre></body></html>";
    exit;
}

output("\n✅ Training completed successfully!\n");

// --- Step 4: Find best.pt and upload model ---
output("\n💾 Step 4: Uploading trained model...\n");

$best_pt = null;
$search_paths = [
    "$tmp_dir/runs/train/weights/best.pt",
    "$tmp_dir/runs/detect/train/weights/best.pt",
];

foreach ($search_paths as $path) {
    if (file_exists($path)) {
        $best_pt = $path;
        break;
    }
}

if (!$best_pt) {
    $find_result = trim(shell_exec("find " . escapeshellarg($tmp_dir) . " -name 'best.pt' -type f 2>/dev/null | head -1"));
    if ($find_result && file_exists($find_result)) {
        $best_pt = $find_result;
    }
}

if (!$best_pt || !file_exists($best_pt)) {
    output("   ❌ Could not find best.pt in training output.\n");
    system("find " . escapeshellarg("$tmp_dir/runs") . " -type f 2>&1");
    output("\nTraining output missing.\n");
    echo "</pre></body></html>";
    exit;
}

output("   Found model: $best_pt (" . round(filesize($best_pt) / 1024 / 1024, 2) . " MB)\n");

try {
    $files_array = [$best_pt];
    $pt_file_path = $best_pt;
    $pt_file = "best.pt";

    output("   Converting to TFJS and inserting into database...\n");

    insert_model_into_db($model_name, $files_array, $pt_file_path, $pt_file, false);

    output("\n✅ Model '$model_name' successfully trained and uploaded!\n");
} catch (\Throwable $e) {
    output("\n❌ Error uploading model: " . $e->getMessage() . "\n");
}

// --- Cleanup ---
output("\n🧹 Cleaning up temporary files...\n");
system("rm -rf " . escapeshellarg($tmp_dir));
output("   Done.\n");

output("\n🎉 All done! Model '$model_name' is now available in the models list.\n");

echo "</pre></body></html>";
?>
