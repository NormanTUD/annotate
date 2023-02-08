<?php
	include_once("functions.php");

	if(array_key_exists("image", $_GET)) {
		mark_as_curated(get_image_id($_GET["image"]));

		print("OK curated");
	} else {
		die("No source given");
	}

?>
