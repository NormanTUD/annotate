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

// Larger padding to force browser to start rendering
echo str_repeat(" ", 4096) . "\n";
flush();

// Include AFTER output has started (no more headers after this point)
include_once("functions.php");
include_once("export_helper.php");

ob_implicit_flush(true);

// --- Validate ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("❌ Error: Use the form button to start training.");
}

$epochs = intval($_POST['epochs'] ?? 50);
$model_yaml = $_POST['model'] ?? 'yolo11s.yaml';
$model_name = trim($_POST['model_name'] ?? 'auto_trained');

if (empty($model_name) || strtolower($model_name) === 'none') {
    die("❌ Error: Please provide a valid model name.");
}

if (!get_number_of_annotated_imgs()) {
    die("❌ Error: No annotated images available for training.");
}

echo "✅ Parameters: model=$model_yaml, epochs=$epochs, name=$model_name\n";
flush();

// --- Step 1: Generate the export ---
echo "\n📦 Step 1: Generating training data...\n";
flush();

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
    die("❌ Error: No annotated images found.");
}

echo "   Found " . count($images) . " annotated images.\n";
flush();

$tmp_dir = create_tmp_dir();
echo "   Working directory: $tmp_dir\n";
flush();

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

echo "   Created dataset.yaml with " . count($category_numbers) . " categories.\n";
flush();

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

echo "   Written " . count($images) . " label files.\n";
flush();

// --- Step 2: Extract images from DB ---
echo "\n🖼️  Step 2: Extracting images from database...\n";
flush();

$img_count = 0;
foreach ($images as $fn => $img) {
    $query = "SELECT image_content FROM image_data WHERE filename = " . esc($fn) . " LIMIT 1";
    $res = rquery($query);
    $row = mysqli_fetch_row($res);
    if ($row && $row[0]) {
        file_put_contents("$tmp_dir/images/$fn", $row[0]);
        $img_count++;
        if ($img_count % 50 == 0) {
            echo "   Extracted $img_count images...\n";
            flush();
        }
    }
}

echo "   ✅ Extracted $img_count images total.\n";
flush();

// --- Step 3: Run YOLO training ---
echo "\n🏃️ Step 3: Starting YOLO training ($epochs epochs, model: $model_yaml)...\n";
flush();

$check_output = [];
exec("python3 -c \"from ultralytics import YOLO; print('ok')\" 2>&1", $check_output, $check_exit);
if ($check_exit !== 0) {
    echo "   ❌ ultralytics not importable: " . implode("\n", $check_output) . "\n";
    die("Training aborted.");
}
echo "   ✅ ultralytics available.\n";
flush();

$imgsz = $GLOBALS["imgsz"] ?? 800;

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
echo "   Command: $train_cmd\n\n";
flush();

$descriptorspec = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];

$process = proc_open($train_cmd, $descriptorspec, $pipes);

if (!is_resource($process)) {
    die("❌ Error: Could not start training process.");
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
        echo $stdout_chunk;
        flush();
        $last_output_time = time();
    }

    if ($stderr_chunk !== false && $stderr_chunk !== "") {
        echo $stderr_chunk;
        flush();
        $last_output_time = time();
    }

    if (!$status['running']) {
        // Drain remaining output
        do {
            $r1 = fread($pipes[1], 8192);
            $r2 = fread($pipes[2], 8192);
            if ($r1) echo $r1;
            if ($r2) echo $r2;
        } while ($r1 || $r2);
        flush();
        break;
    }

    if ((time() - $last_output_time) > $timeout) {
        echo "\n⚠️ No output for {$timeout}s, killing process...\n";
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
    echo "\n❌ Training failed with exit code $exit_code\n";
    echo "   Temporary files preserved at: $tmp_dir\n";
    die("Training failed.");
}

echo "\n✅ Training completed successfully!\n";
flush();

// --- Step 4: Find best.pt and upload model ---
echo "\n💾 Step 4: Uploading trained model...\n";
flush();

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
    echo "   ❌ Could not find best.pt in training output.\n";
    system("find " . escapeshellarg("$tmp_dir/runs") . " -type f 2>&1");
    die("\nTraining output missing.");
}

echo "   Found model: $best_pt (" . round(filesize($best_pt) / 1024 / 1024, 2) . " MB)\n";
flush();

try {
    $files_array = [$best_pt];
    $pt_file_path = $best_pt;
    $pt_file = "best.pt";

    echo "   Converting to TFJS and inserting into database...\n";
    flush();

    insert_model_into_db($model_name, $files_array, $pt_file_path, $pt_file, false);

    echo "\n✅ Model '$model_name' successfully trained and uploaded!\n";
    flush();
} catch (\Throwable $e) {
    echo "\n❌ Error uploading model: " . $e->getMessage() . "\n";
    flush();
}

// --- Cleanup ---
echo "\n🧹 Cleaning up temporary files...\n";
system("rm -rf " . escapeshellarg($tmp_dir));
echo "   Done.\n";

echo "\n🎉 All done! Model '$model_name' is now available in the models list.\n";
?>
