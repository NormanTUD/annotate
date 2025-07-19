<?php
include("header.php");
include_once("functions.php");

ini_set('memory_limit', '16384M');
ini_set('max_execution_time', '3600');
set_time_limit(3600);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_FILES['tfjs_model']) && isset($_POST['model_name'])) {
		$modelName = $_POST['model_name'];

		if (empty($modelName)) {
			die("Error: Model name is empty.");
		}

		$has_labels = 0;
		$has_model = 0;
		$model_json_path = "";
		$file_paths = [];

		if (!is_array($_FILES["tfjs_model"]["name"])) {
			die("Error: Invalid file input structure.");
		}

		$files = [];
		for ($i = 0; $i < count($_FILES["tfjs_model"]["name"]); $i++) {
			$filename = $_FILES["tfjs_model"]["name"][$i];
			$tmp = $_FILES["tfjs_model"]["tmp_name"][$i];
			$error = $_FILES["tfjs_model"]["error"][$i];

			if ($error !== UPLOAD_ERR_OK) {
				die("Error: File upload failed for '$filename' with error code $error.");
			}

			if (empty($filename)) {
				die("Error: Empty filename at index $i.");
			}

			if (empty($tmp) || !file_exists($tmp)) {
				die("Error: Temporary file '$tmp' does not exist.");
			}

			$tmp_path = preg_replace("/\/[^\/]*$/", "/", $tmp);
			if ($tmp_path === null) {
				die("Error: Failed to determine path for '$tmp'.");
			}

			$new_path = "$tmp_path$filename";

			if (empty($new_path)) {
				die("Error: new_path is empty for file '$filename'.");
			}

			if (!copy($tmp, $new_path)) {
				die("Error: Failed to copy '$tmp' to '$new_path'.");
			}

			$files[] = $new_path;
			$file_paths[] = $filename;

			if ($filename === "labels.json") {
				$has_labels = 1;

				$labels_json = @file_get_contents($tmp);
				if ($labels_json === false) {
					die("Error: Unable to read labels.json");
				}

				$labels_content = json_decode($labels_json);
				if ($labels_content === null) {
					die("Error: Failed to decode labels.json (invalid JSON).");
				}

				for ($k = 0; $k < count($labels_content); $k++) {
					get_or_create_category_id($labels_content[$k]);
				}
			} else if ($filename === "model.json") {
				$has_model = 1;
				$model_json_path = $tmp;
			}
		}

		if (!$has_model || !$has_labels) {
			if (!$has_model) {
				echo "<b><tt>model.json</tt> is missing!</b><br>";
			}

			if (!$has_labels) {
				echo "<b><tt>labels.json</tt> is missing!</b><br>";
			}
			die("Error: Required files missing.");
		}

		$model_json_raw = @file_get_contents($model_json_path);
		if ($model_json_raw === false) {
			die("Error: Unable to read model.json");
		}

		$model_json = json_decode($model_json_raw, true);
		if ($model_json === null) {
			die("Error: Failed to decode model.json (invalid JSON).");
		}

		if (isset($model_json["weightsManifest"][0]["paths"])) {
			$paths = $model_json["weightsManifest"][0]["paths"];
			$missing_files = [];

			for ($kk = 0; $kk < count($paths); $kk++) {
				if (!in_array($paths[$kk], $file_paths)) {
					$missing_files[] = $paths[$kk];
				}
			}

			if (count($missing_files)) {
				die("Missing files to be uploaded: <tt>" . join("</tt>, <tt>", $missing_files) . "</tt>");
			}
		} else {
			die("Error: model.json does not contain expected weightsManifest->paths structure.");
		}

		if (count($files)) {
			try {
				insert_model_into_db($modelName, $files);
				echo "Success: Model saved into DB";
			} catch (\Throwable $e) {
				die("Error while inserting model into DB: " . $e->getMessage());
			}
		} else {
			die("Error: No files processed for insertion.");
		}
	} else {
		die("Error: Please provide both a model name and at least one file.");
	}
} else {
	die("Error: REQUEST_METHOD is not POST, but " . $_SERVER['REQUEST_METHOD']);
}

include_once("footer.php");
?>
