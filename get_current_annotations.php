<?php
	include_once("functions.php");

	if(array_key_exists("source", $_GET)) {
		$hash_filename = hash("sha256", $_GET["source"]);
		$dir = "annotations/$hash_filename/$user_id/";

		if(is_dir($dir)) {
			$files = scandir($dir);
			$jsons = array();

			foreach($files as $file) {
				if(preg_match("/\.json$/", $file)) {
					$jsons[] = json_decode(json_decode(file_get_contents("$dir/$file"), true)["full"]);
				}
			}
			print json_encode($jsons);
		} else {
			//die("$dir does not exist");
			print("[]");
			exit(1);
		}
	} else {
		print("[]");
		exit(2);
	}

?>
