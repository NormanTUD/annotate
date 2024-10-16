<?php
	include("header.php");
	include_once("functions.php");

	ini_set('memory_limit', '16384M');
	ini_set('max_execution_time', '3600');
	set_time_limit(3600);

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		if (isset($_FILES['tfjs_model']) && isset($_POST['model_name'])) {
			$modelName = $_POST['model_name'];

			$has_labels = 0;
			$has_model = 0;
			$model_json_path = "";
			$file_paths = [];

			$files = [];
			for ($i = 0; $i < count($_FILES["tfjs_model"]["name"]); $i++) {
				$filename = $_FILES["tfjs_model"]["name"][$i];
				$tmp = $_FILES["tfjs_model"]["tmp_name"][$i];

				$tmp_path = $tmp;
				$tmp_path = preg_replace("/\/[^\/]*$/", "/", $tmp_path);

				$new_path = "$tmp_path$filename";

                if(!$new_path) {
                    print("new_path path cannot be empty");
                    exit(1);
                }

                if(!$tmp) {
                    print("tmp path cannot be empty");
                    exit(1);
                }

				copy($tmp, $new_path);

				$files[] = $new_path;

				if($filename == "labels.json") {
					$has_labels = 1;

					$labels_content = json_decode(file_get_contents($tmp));

					for ($k = 0; $k < count($labels_content); $k++) {
						get_or_create_category_id($labels_content[$k]);
					}
				} else if($filename == "model.json") {
					$has_model = 1;

					$model_json_path = $tmp;
				}

				$file_paths[] = $filename;
			}

			if(!$has_labels || !$has_model) {
				if(!$has_model) {
					echo "<b><tt>model.json</tt> is missing!</b><br>";
				}

				if($has_labels) {
					echo "<b><tt>labels.json</tt> is missing!</b><br>";
				}
			} else {
				$model_json = json_decode(file_get_contents($model_json_path), 1);

				if(isset($model_json["weightsManifest"][0]["paths"])) {
					$paths = $model_json["weightsManifest"][0]["paths"];

					$missing_files = [];

					for ($kk = 0; $kk < count($paths); $kk++) {
						if(!in_array($paths[$kk], $file_paths)) {
							$missing_files[] = $paths[$kk];
						}
					}

					if(count($missing_files)) {
						die("Missing files to be uploaded: <tt>".join("</tt>, <tt>", $missing_files)."</tt>");
					}
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
			}
		} else {
			echo "Error: Please provide both a model name and a .pt file to upload.";
		}
	}

	include_once("footer.php");
?>
