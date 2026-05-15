<?php
	include_once("functions.php");

	if(array_key_exists("image", $_GET)) {
		$image_id = get_image_id($_GET["image"]);
		if(preg_match("/^\d+$/", $image_id)) {
			delete_all_annos_from_image($image_id);
			print("OK Deleted");
		} else {
			die("Image not found");
		}
	} else {
		die("No source given");
	}
?>
