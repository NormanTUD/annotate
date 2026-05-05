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
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('X-Accel-Buffering: no');
header('Content-Encoding: none');

// Padding to force browser to start rendering
echo str_repeat(" ", 4096) . "\n";
flush();

// Include AFTER output has started (no more headers after this point)
include_once("functions.php");
include_once("export_helper.php");

ob_implicit_flush(true);

/**
 * Strip ANSI escape codes from text for clean plain-text output
 */
function strip_ansi($text) {
    // Remove ESC[K (erase to end of line)
    $text = preg_replace('/\x1b\[K/', '', $text);

    // Remove carriage return based overwrites (progress bar redraws)
    // Keep only the last segment after \r on each line
    $text = preg_replace('/^.*\r(?!\n)/m', '', $text);

    // Remove all ANSI escape sequences (ESC[ ... letter)
    $text = preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $text);

    // Remove orphaned bracket codes where ESC char was lost in transit
    // e.g. [34m, [1m, [0m, [K
    $text = preg_replace('/\[([0-9;]*)[mKHJG]/', '', $text);

    return $text;
}

/**
 * Output text with ANSI codes stripped
 */
function output($text) {
    echo strip_ansi($text);
    flush();
}

// --- Validate ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    output("❌ Error: Use the form button to start training.\n");
    exit;
}

$epochs = intval($_POST['epochs'] ?? 50);
$model_yaml = $_POST['model'] ?? 'yolo11s.yaml';
$model_name = trim($_POST['model_name'] ?? 'auto_trained');
$fine_tune = isset($_POST['fine_tune']) && $_POST['fine_tune'] == '1';

// Determine the model to load
if ($fine_tune) {
    // Derive the .pt filename from the selected .yaml model size
    // e.g. yolo11n.yaml -> yolo11n.pt, yolo11s.yaml -> yolo11s.pt, etc.
    $pt_filename = preg_replace('/\.yaml$/', '.pt', $model_yaml);
    $model_to_load = "/tmp/$pt_filename";
    $training_mode = 'fine-tune';
    
    // Pre-download the model if it doesn't exist in /tmp
    if (!file_exists($model_to_load)) {
        output("   ⬇️  Downloading pretrained model ($pt_filename) to $model_to_load...\n");
        $download_url = "https://github.com/ultralytics/assets/releases/download/v8.4.0/$pt_filename";
        $dl_cmd = "curl -L -o " . escapeshellarg($model_to_load) . " " . escapeshellarg($download_url) . " 2>&1";
        $dl_output = shell_exec($dl_cmd);
        if (!file_exists($model_to_load) || filesize($model_to_load) < 100000) {
            output("   ❌ Failed to download pretrained model.\n");
            output("   $dl_output\n");
            output("   You can manually download it and place it at $model_to_load\n");
            exit;
        }
        output("   ✅ Downloaded pretrained model (" . round(filesize($model_to_load) / 1024 / 1024, 2) . " MB)\n");
    } else {
        output("   ✅ Pretrained model already cached at $model_to_load (" . round(filesize($model_to_load) / 1024 / 1024, 2) . " MB)\n");
    }
} else {
    // Train from scratch using architecture definition
    $model_to_load = $model_yaml;
    $training_mode = 'from-scratch';
}

if (empty($model_name) || strtolower($model_name) === 'none') {
    output("❌ Error: Please provide a valid model name.\n");
    exit;
}

if (!get_number_of_annotated_imgs()) {
    output("❌ Error: No annotated images available for training.\n");
    exit;
}

output("✅ Parameters: model=$model_to_load, epochs=$epochs, name=$model_name, mode=$training_mode\n");
if ($fine_tune) {
    output("   ℹ️  Fine-tuning from pretrained COCO weights (80 everyday object classes)\n");
}

// --- Step 1: Generate the export ---
output("\n📦 Step 1: Generating training data...\n");

$_GET['epochs'] = $epochs;
$_GET['model'] = $model_to_load;
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
output("\n🏃 Step 3: Starting YOLO training ($epochs epochs, model: $model_to_load, mode: $training_mode)...\n");

$check_output = [];
exec("python3 -c \"from ultralytics import YOLO; print('ok')\" 2>&1", $check_output, $check_exit);
if ($check_exit !== 0) {
	output("   ❌ ultralytics not importable: " . implode("\n", $check_output) . "\n");
	output("Training aborted.\n");
	exit;
}
output("   ✅ ultralytics available.\n");

$imgsz = $GLOBALS["imgsz"] ?? 800;

echo "Image-Size: $imgsz\n";

$train_script = <<<PYTHON
import os, sys
sys.stdout.reconfigure(line_buffering=True)
sys.stderr.reconfigure(line_buffering=True)

# Fix permission errors for matplotlib and other config dirs
os.environ["MPLCONFIGDIR"] = "/tmp/matplotlib_config"
os.environ["YOLO_CONFIG_DIR"] = "/tmp/Ultralytics"
os.environ["HOME"] = "/tmp"

os.makedirs("/tmp/matplotlib_config", exist_ok=True)

from ultralytics import YOLO

print("Loading model: $model_to_load (mode: $training_mode)", flush=True)
model = YOLO("$model_to_load")

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

$train_script_path = "$tmp_dir/train.py";
file_put_contents($train_script_path, $train_script);

$train_cmd = "PYTHONUNBUFFERED=1 MPLCONFIGDIR=/tmp/matplotlib_config HOME=/tmp python3 " . escapeshellarg($train_script_path) . " 2>&1";
output("   Command: $train_cmd\n\n");

$descriptorspec = [
	0 => ["pipe", "r"],
	1 => ["pipe", "w"],
	2 => ["pipe", "w"]
];

$process = proc_open($train_cmd, $descriptorspec, $pipes);

if (!is_resource($process)) {
	output("❌ Error: Could not start training process.\n");
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
			$r1 = fread($pipes[1], 8192);
			$r2 = fread($pipes[2], 8192);
			if ($r1) output($r1);
			if ($r2) output($r2);
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
?>
