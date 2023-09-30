<?php
	include_once("functions.php");

	if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
		// Loop through each uploaded file
		for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
			$file_name = $_FILES['images']['name'][$i];
			$file_tmp = $_FILES['images']['tmp_name'][$i];

			// Call the function to insert the image into the database
			insert_image_into_db($file_tmp, $file_name);
		}
		echo "Images uploaded successfully.";
	} else {
		echo "Please select one or more images to upload.";
	}
?>
