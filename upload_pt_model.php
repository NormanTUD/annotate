<?php
	ini_set('memory_limit', '16384M');
	ini_set('max_execution_time', '3600');
	set_time_limit(3600);

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

					// Read and print the output live
					while (true) {
						$output = stream_get_contents($pipes[1]);
						if ($output === false) {
							break;
						}

						// Print the output and immediately flush
						echo nl2br(htmlspecialchars($output));
						ob_flush();
						flush();
					}

					// Close the pipes and get the return code
					fclose($pipes[0]);
					fclose($pipes[1]);
					fclose($pipes[2]);
					$returnCode = proc_close($process);

					if ($returnCode === 0) {
						// Loop through the generated files and insert them into the database
						foreach (glob('yolov5/*.tfjs') as $tfjsFile) {
							try {
								insert_model_into_db($modelName, $tfjsFile, pathinfo($tfjsFile, PATHINFO_BASENAME));
							} catch (\Throwable $e) {
								echo "Error: <pre>$e</pre>";
							}
						}

						// Display success message
						success("Model Upload", "Model uploaded and processed successfully.");
					} else {
						echo "Error: Python script execution failed.";
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
	}
?>
