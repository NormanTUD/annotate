<?php
	include_once("functions.php");

	ini_set('memory_limit', '16384M');
	ini_set('max_execution_time', '3600');
	set_time_limit(3600);

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		if (isset($_FILES['tfjs_model']) && isset($_POST['model_name'])) {
			$modelName = $_POST['model_name'];

			$files = [];
			for ($i = 0; $i < count($_FILES["tfjs_model"]["name"]) - 1; $i++) {
				$filename = $_FILES["tfjs_model"]["name"][$i];
				$tmp = $_FILES["tfjs_model"]["tmp_name"][$i];

				$tmp_path = $tmp;
				$tmp_path = preg_replace("/\/[^\/]*$/", "/", $tmp_path);

				$new_path = "$tmp_path$filename";

				copy($tmp, $new_path);

				$files[] = $new_path;
			}

			if(count($files)) {
				try {
					insert_model_into_db($modelName, $files);
					echo "Success: Model saved into DB";
				} catch (\Throwable $e) {
					echo "Error: $e";
				}
			} else {
				echo "Error: no files found";
			}
		} else {
			echo "Error: Please provide both a model name and a .pt file to upload.";
		}
	}
?>
