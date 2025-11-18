<?php
include("header.php");
include_once("functions.php");

ini_set('memory_limit', '16384M');
ini_set('max_execution_time', '3600');
set_time_limit(3600);

function handle_tfjs_folder_upload($folder_path, $modelName) {
    $files = [];
    $file_paths = [];
    $has_labels = 0;
    $has_model = 0;
    $model_json_path = "";

    if (!is_dir($folder_path)) {
        die("Error: Converted folder path '$folder_path' does not exist.");
    }

    $dir_files = scandir($folder_path);
    foreach ($dir_files as $file) {
        if ($file === '.' || $file === '..') continue;
        $full_path = $folder_path . DIRECTORY_SEPARATOR . $file;
        if (!is_file($full_path)) continue;

        $files[] = $full_path;
        $file_paths[] = $file;

        if ($file === "labels.json") {
            $has_labels = 1;
            $labels_content = json_decode(file_get_contents($full_path));
            if ($labels_content === null) {
                die("Error: Failed to decode labels.json in converted folder.");
            }
            foreach ($labels_content as $label) {
                get_or_create_category_id($label);
            }
        } else if ($file === "model.json") {
            $has_model = 1;
            $model_json_path = $full_path;
        }
    }

    if (!$has_model || !$has_labels) {
        if (!$has_model) echo "<b><tt>model.json</tt> missing!</b><br>";
        if (!$has_labels) echo "<b><tt>labels.json</tt> missing!</b><br>";
        die("Error: Required files missing in converted folder.");
    }

    $model_json_raw = file_get_contents($model_json_path);
    $model_json = json_decode($model_json_raw, true);
    if ($model_json === null) die("Error: Invalid model.json in converted folder.");

    if (isset($model_json["weightsManifest"][0]["paths"])) {
        $paths = $model_json["weightsManifest"][0]["paths"];
        $missing_files = array_diff($paths, $file_paths);
        if (count($missing_files)) {
            die("Missing files in converted folder: <tt>" . join("</tt>, <tt>", $missing_files) . "</tt>");
        }
    } else {
        die("Error: model.json in converted folder lacks expected weightsManifest->paths.");
    }

    try {
        insert_model_into_db($modelName, $files);
        echo "Success: Converted model '$modelName' saved into DB";
    } catch (\Throwable $e) {
        die("Error while inserting converted model into DB: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Fall: PyTorch .pt Model zur Konvertierung
    if (!empty($_POST['pt_model_path']) && !empty($_POST['model_name'])) {
        $pt_model_path = escapeshellarg($_POST['pt_model_path']);
        $modelName = trim($_POST['model_name']);

        $command = "bash convert_to_tfjs $pt_model_path";
        ob_start();
        system($command, $retval);
        $output = ob_get_clean();

        $converted_path = null;
        if (preg_match('/CONVERTED_MODEL_PATH:\s*(.+)/', $output, $matches)) {
            $converted_path = trim($matches[1]);
        }

        if ($converted_path && is_dir($converted_path)) {
            handle_tfjs_folder_upload($converted_path, $modelName);
        } else {
            die("Conversion failed or path not found. Output:<br><pre>$output</pre>");
        }
        exit;
    }

    // 2) Fall: Standard-Upload über $_FILES
    if (isset($_FILES['tfjs_model']) && isset($_POST['model_name'])) {
        $modelName = $_POST['model_name'];
        if (empty($modelName)) die("Error: Model name is empty.");

        $has_labels = 0;
        $has_model = 0;
        $model_json_path = "";
        $file_paths = [];
        $files = [];

        if (!is_array($_FILES["tfjs_model"]["name"])) die("Error: Invalid file input structure.");

        for ($i = 0; $i < count($_FILES["tfjs_model"]["name"]); $i++) {
            $filename = $_FILES["tfjs_model"]["name"][$i];
            $tmp = $_FILES["tfjs_model"]["tmp_name"][$i];
            $error = $_FILES["tfjs_model"]["error"][$i];

            if ($error !== UPLOAD_ERR_OK) die("Error: Upload failed for '$filename' (code $error).");
            if (empty($filename) || empty($tmp) || !file_exists($tmp)) die("Error: File '$filename' missing temporary file.");

            $tmp_path = preg_replace("/\/[^\/]*$/", "/", $tmp);
            $new_path = "$tmp_path$filename";
            if (!copy($tmp, $new_path)) die("Error: Failed to copy '$tmp' to '$new_path'.");

            $files[] = $new_path;
            $file_paths[] = $filename;

            if ($filename === "labels.json") {
                $has_labels = 1;
                $labels_content = json_decode(file_get_contents($tmp));
                if ($labels_content === null) die("Error: Invalid labels.json");
                foreach ($labels_content as $label) get_or_create_category_id($label);
            } else if ($filename === "model.json") {
                $has_model = 1;
                $model_json_path = $tmp;
            }
        }

        if (!$has_model || !$has_labels) {
            if (!$has_model) echo "<b><tt>model.json</tt> is missing!</b><br>";
            if (!$has_labels) echo "<b><tt>labels.json</tt> is missing!</b><br>";
            die("Error: Required files missing.");
        }

        $model_json_raw = file_get_contents($model_json_path);
        $model_json = json_decode($model_json_raw, true);
        if ($model_json === null) die("Error: Invalid model.json");

        if (isset($model_json["weightsManifest"][0]["paths"])) {
            $paths = $model_json["weightsManifest"][0]["paths"];
            $missing_files = array_diff($paths, $file_paths);
            if (count($missing_files)) die("Missing files: <tt>" . join("</tt>, <tt>", $missing_files) . "</tt>");
        } else die("Error: model.json does not contain expected weightsManifest->paths structure.");

        try {
            insert_model_into_db($modelName, $files);
            echo "Success: Model '$modelName' saved into DB";
        } catch (\Throwable $e) {
            die("Error while inserting model into DB: " . $e->getMessage());
        }

    } else {
        die("Error: Please provide both a model name and at least one file, or a PyTorch model path.");
    }
} else {
    die("Error: REQUEST_METHOD is not POST, but " . $_SERVER['REQUEST_METHOD']);
}

include_once("footer.php");
?>
