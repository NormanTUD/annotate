<?php
	include_once("functions.php");

	ini_set('memory_limit', '16384M');
	ini_set('max_execution_time', '3600');
	set_time_limit(3600);

	function insert_model_into_db ($model_name, $files_array) {
		try {
			// Establish a database connection (replace with your actual database details)
			$pdo = new PDO("mysql:host=".$GLOBALS["db_host"].";dbname=".$GLOBALS["db_name"], $GLOBALS["db_username"], $GLOBALS["db_password"]);

			// Initialize an array to store the IDs of inserted models
			$inserted_model_ids = [];

			// Loop through the files array
			foreach ($files_array as $path) {
				// Generate a unique filename to avoid conflicts
				$file = $path;
				$file = preg_replace("/.*\//", "", $file);
				$file_contents = file_get_contents($path);

				// Insert the model into the database
				$stmt = $pdo->prepare("INSERT INTO models (model_name, upload_time, filename, file_contents) VALUES (:model_name, now(), :filename, :file_contents)");
				$stmt->bindParam(':model_name', $model_name);
				$stmt->bindParam(':filename', $file);
				$stmt->bindParam(':file_contents', $file_contents, PDO::PARAM_LOB);
				$stmt->execute();

				// Retrieve the ID of the inserted model
				$model_id = $pdo->lastInsertId();

				echo "Model-ID: $model_id<br>";

				// Store the ID in the array
				$inserted_model_ids[] = $model_id;
			}

			// Close the database connection
			$pdo = null;

			return $inserted_model_ids;
		} catch (\Throwable $e) {
			// Log and handle the database error
			error_log("Database error: " . $e->getMessage());
			die("Error: Unable to insert models into the database.<br>".$e->getMessage());
		}
	}

	function process_is_running ($process) {
		$res = proc_get_status($process);

		return $res["running"];
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		print "<pre>";
		if (isset($_FILES['pytorch_model']) && isset($_POST['model_name'])) {
			$modelName = $_POST['model_name'];
			$pytorchModelName = $_FILES['pytorch_model']['name'];
			$pytorchModelTmp = $_FILES['pytorch_model']['tmp_name'];

			// Check if the uploaded file is a .pt file
			if (pathinfo($pytorchModelName, PATHINFO_EXTENSION) === 'pt') {
				// Define the command to run the Python script
				$pythonCommand = 'bash pt_to_tfjs.sh '.$pytorchModelTmp;

				// Open a pipe to the command and set it to non-blocking mode
				$descriptorspec = array(
					0 => array("pipe", "r"),
					1 => array("pipe", "w"),
					2 => array("pipe", "w"),
				);

				$process = proc_open($pythonCommand, $descriptorspec, $pipes);

				if (is_resource($process)) {
					// Set stream to non-blocking mode
					stream_set_blocking($pipes[1], 0);

					$output_path = "";

					// Read and print the output live
					while (process_is_running($process)) {
						$output = stream_get_contents($pipes[1]);
						if ($output === false) {
							break;
						}

						if(preg_match('/>>PATH>>(.*)<<PATH<</', $output, $matches)) {
							$output_path = $matches[1];
							echo "<b>output path $output_path detected</b><br>";
						} else {
							echo nl2br(htmlspecialchars($output));
						}
						ob_flush();
						flush();
					}

					// Close the pipes and get the return code
					fclose($pipes[0]);
					fclose($pipes[1]);
					fclose($pipes[2]);
					$returnCode = proc_close($process);

					// Loop through the generated files and insert them into the database
					if($output_path) {
						$files = [];
						foreach (glob("$output_path/*") as $tfjsFile) {
							$files[] = $tfjsFile;
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
						print("$output_path could not be determined");
					}
				} else {
					echo "Error: Failed to open a process for the Python script.";
				}
			} else {
				echo "Error: Please upload a valid .pt file.";
			}
		} else {
			echo "Error: Please provide both a model name and a .pt file to upload.";
		}
		print "</pre>";
	}
?>
