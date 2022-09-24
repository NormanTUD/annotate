<?php
	include_once("functions.php");

	if(array_key_exists("source", $_POST)) {
		if(array_key_exists("id", $_POST)) {
			$hash_filename = hash("sha256", $_POST["source"]);
			$hash_annotation = hash("sha256", $_POST["id"]);

			$dir = "annotations/$hash_filename/$user_id/";
			$filename = "$dir/$hash_annotation.json";

			# sudo mkdir annotations
			# cd annotations
			# sudo chown -R www-data:$USER .
			ob_start();
			system("mkdir -p $dir");
			ob_clean();

			if(is_dir($dir)) {
				file_put_contents($filename, json_encode($_POST));
				if(file_exists($filename)) {
					print "OK";
				} else {
					die("$filename could not be created");
				}
			} else {
				die("$dir could not be created");
			}
		} else {
			die("No ID given");
		}
	} else {
		die("No source given");
	}

?>
