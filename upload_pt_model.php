<?php
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		if (isset($_FILES['pytorch_model']) && isset($_POST['model_name'])) {
			$modelName = $_POST['model_name'];
			$pytorchModelName = $_FILES['pytorch_model']['name'];
			$pytorchModelTmp = $_FILES['pytorch_model']['tmp_name'];

			// Check if the uploaded file is a .pt file
			if (pathinfo($pytorchModelName, PATHINFO_EXTENSION) === 'pt') {
				// Define the command to run the Python script
				$pythonCommand = 'bash pt_to_tfjs.sh '.$pytorchModelTmp;
				print "<pre>$pythonCommand</pre>";

				// Execute the Python script
				exec($pythonCommand, $output, $returnCode);

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
				echo "Error: Please upload a valid .pt file.";
			}
		} else {
			echo "Error: Please provide both a model name and a .pt file to upload.";
		}
	}
?>
