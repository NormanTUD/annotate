<?php
	include_once("functions.php");

	$first_other = 0;
	if(array_key_exists("first_other", $_GET)) {
		$first_other = 1;
	}

	if(array_key_exists("source", $_GET)) {
		$hash_filename = hash("sha256", $_GET["source"]);
		$base_dir = "annotations/$hash_filename";
		if(!$first_other) {
			$dir = "$base_dir/$user_id/";

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
				print("[]");
				exit(1);
			}
		} else {
			$users = scandir($base_dir);
			foreach($users as $user) {
				if($user != "." && $user != "..") {
					$dir = "$base_dir/$user";
					if(is_dir($dir)) {
						$files = scandir($dir);
						$jsons = array();

						foreach($files as $file) {
							if(preg_match("/\.json$/", $file)) {
								$jsons[] = json_decode(json_decode(file_get_contents("$dir/$file"), true)["full"]);
							}
						}
						print json_encode($jsons);
						exit(0);
					} else {
						print("[]");
						exit(1);
					}
				}
			}
		}
	} else {
		print("[]");
		exit(2);
	}

?>
