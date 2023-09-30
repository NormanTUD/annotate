<?php
	ini_set('memory_limit', '16384M');
	ini_set('max_execution_time', '3600');
	set_time_limit(3600);

	function process_is_running ($process) {
		$res = proc_get_status($process);

		return $res["running"];
	}

	function bashColorToHtml($string) {
		$colors = [
			'/\[0;30m(.*?)\[0m/s' => '<span class="black">$1</span>',
			'/\[0;31m(.*?)\[0m/s' => '<span class="red">$1</span>',
			'/\[0;32m(.*?)\[0m/s' => '<span class="green">$1</span>',
			'/\[0;33m(.*?)\[0m/s' => '<span class="brown">$1</span>',
			'/\[0;34m(.*?)\[0m/s' => '<span class="blue">$1</span>',
			'/\[0;35m(.*?)\[0m/s' => '<span class="purple">$1</span>',
			'/\[0;36m(.*?)\[0m/s' => '<span class="cyan">$1</span>',
			'/\[0;37m(.*?)\[0m/s' => '<span class="light-gray">$1</span>',

			'/\[1;30m(.*?)\[0m/s' => '<span class="dark-gray">$1</span>',
			'/\[1;31m(.*?)\[0m/s' => '<span class="light-red">$1</span>',
			'/\[1;32m(.*?)\[0m/s' => '<span class="light-green">$1</span>',
			'/\[1;33m(.*?)\[0m/s' => '<span class="yellow">$1</span>',
			'/\[1;34m(.*?)\[0m/s' => '<span class="light-blue">$1</span>',
			'/\[1;35m(.*?)\[0m/s' => '<span class="light-purple">$1</span>',
			'/\[1;36m(.*?)\[0m/s' => '<span class="light-cyan">$1</span>',
			'/\[1;37m(.*?)\[0m/s' => '<span class="white">$1</span>',
		];

		return preg_replace(array_keys($colors), $colors, $string);
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

					// Read and print the output live
					while (process_is_running($process)) {
						$output = stream_get_contents($pipes[1]);
						if ($output === false) {
							break;
						}

						// Print the output and immediately flush
						echo nl2br(bashColorToHtml(htmlspecialchars($output)));
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
								echo "Error: $e";
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
		print "</pre>";
	}
?>
