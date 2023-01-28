<?php
	include_once("functions.php");

	if(array_key_exists("source", $_POST)) {
		if(array_key_exists("id", $_POST)) {
			$hash_filename = hash("sha256", $_POST["source"]);
			$hash_annotation = hash("sha256", $_POST["id"]);

			$dir = "annotations/$hash_filename/$user_id/";
			$filename = "$dir/$hash_annotation.json";


			print("OK Ignored");
		} else {
			die("No ID given");
		}
	} else {
		die("No source given");
	}

?>
