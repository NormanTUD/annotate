<?php
	include_once("functions.php");

	if(array_key_exists("image", $_GET)) {
		flag_all_annos_as_deleted(get_image_id($_GET["image"]));

		print("OK Deleted");
	} else {
		die("No source given");
	}

?>
