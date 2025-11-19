<?php
//include("header.php");
include_once("functions.php");

// --- Unlimited execution time ---
ini_set('memory_limit', '-1');        // Unbegrenzter Speicher
ini_set('max_execution_time', '0');   // Kein Zeitlimit
set_time_limit(0);                     // Auch auf 0 setzen, falls ini nicht greift

// --- Output direkt an Browser senden ---
while (ob_get_level() > 0) ob_end_flush(); // Alle Output Buffering Ebenen beenden
ob_implicit_flush(true);
header('Content-Type: text/html; charset=utf-8');

// --- Optional: Browser am Leben halten ---
echo str_repeat(' ', 1024); // Sendet 1KB, Browser beginnt zu rendern
flush();

// --- Request Handling ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	die("❌Error: REQUEST_METHOD is not POST, but " . $_SERVER['REQUEST_METHOD']);
}

$has_model_uploaded = false;
$files = [];
$modelName = "";

// --- TFJS Upload ---
if (isset($_FILES['tfjs_model']) && isset($_POST['model_name'])) {
	$modelName = $_POST['model_name'];
	if (empty($modelName)) die("❌Error: Model name is empty.");

	if (!is_array($_FILES["tfjs_model"]["name"])) die("❌Error: Invalid file input structure.");

	$has_labels = false;
	$has_model = false;
	$model_json_path = "";
	$file_paths = [];

	for ($i = 0; $i < count($_FILES["tfjs_model"]["name"]); $i++) {
		$filename = $_FILES["tfjs_model"]["name"][$i];
		$tmp = $_FILES["tfjs_model"]["tmp_name"][$i];
		$error = $_FILES["tfjs_model"]["error"][$i];

		if ($error !== UPLOAD_ERR_OK) die("❌Error: Upload failed for '$filename' (code $error).");
		if (empty($filename) || empty($tmp) || !file_exists($tmp)) die("❌Error: File '$filename' missing temporary file.");

		$tmp_path = preg_replace("/\/[^\/]*$/", "/", $tmp);
		$new_path = "$tmp_path$filename";
		if (!copy($tmp, $new_path)) die("❌Error: Failed to copy '$tmp' to '$new_path'.");

		$files[] = $new_path;
		$file_paths[] = $filename;

		if ($filename === "labels.json") {
			$has_labels = true;
			$labels_content = json_decode(file_get_contents($tmp));
			if ($labels_content === null) die("❌Error: Invalid labels.json");
			foreach ($labels_content as $label) get_or_create_category_id($label);
		} else if ($filename === "model.json") {
			$has_model = true;
			$model_json_path = $tmp;
		}

		// --- Fortschritt ausgeben, Browser flushen ---
		echo "Uploaded: $filename<br>";
		flush();
	}

	if (!$has_model || !$has_labels) {
		if (!$has_model) echo "<b><tt>model.json</tt> is missing!</b><br>";
		if (!$has_labels) echo "<b><tt>labels.json</tt> is missing!</b><br>";
		die("❌Error: Required files missing.");
	}

	$model_json_raw = file_get_contents($model_json_path);
	$model_json = json_decode($model_json_raw, true);
	if ($model_json === null) die("❌Error: Invalid model.json");

	if (isset($model_json["weightsManifest"][0]["paths"])) {
		$paths = $model_json["weightsManifest"][0]["paths"];
		$missing_files = array_diff($paths, $file_paths);
		if (count($missing_files)) die("Missing files: <tt>" . join("</tt>, <tt>", $missing_files) . "</tt>");
	} else die("❌Error: model.json does not contain expected weightsManifest->paths structure.");

	$has_model_uploaded = true;
}

// --- PyTorch Upload ---
else if (isset($_FILES['pt_model_file'])) {
	$file = $_FILES['pt_model_file'];
	if ($file['error'] !== UPLOAD_ERR_OK) die("❌Error: Upload failed for '" . $file['name'] . "' (code " . $file['error'] . ").");
	$files[] = $file['tmp_name'];
	$modelName = $_POST['model_name'];
	$has_model_uploaded = true;
}

// --- Model in DB speichern ---
if (!$has_model_uploaded) {
	die("❌Error: Please provide both a TFJS model name + files, or a PyTorch model file.");
}

try {
	$pt_file = isset($_FILES['pt_model_file']) ? $_FILES['pt_model_file'] : '';
	insert_model_into_db($modelName, $files, $pt_file);
	echo "✅ Success: Model '$modelName' saved into DB<br>";
	flush();
} catch (\Throwable $e) {
	die("❌Error while inserting model into DB: " . $e->getMessage());
}

include_once("footer.php");
?>
