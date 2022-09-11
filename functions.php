<?php
	$user_id = hash("sha256", $_SERVER['REMOTE_ADDR']);
	if(array_key_exists("annotate_userid", $_COOKIE) && preg_match("/^([a-f0-9]{64})$/", $_COOKIE["annotate_userid"])) {
		$user_id = $_COOKIE["annotate_userid"];
	} else {
		setcookie("annotate_userid", $user_id);
	}

	function dier($msg) {
		print_r($msg);
		exit(1);
	}

	function number_of_annotations ($uid, $img) {
		$img = hash("sha256", $img);
		$dir = "annotations/$img/$uid/";

		if(is_dir($dir)) {
			$files = scandir($dir);

			$i = 0;

			foreach($files as $file) {
				if(preg_match("/\.json$/", $file)) {
					$i++;
				}
			}

			return $i;
		}
		return 0;
	}


	function img_has_annotation ($uid, $img) {
		$img = hash("sha256", $img);
		$dir = "annotations/$img/$uid/";

		$files = scandir($dir);

		foreach($files as $file) {
			if(preg_match("/\.json$/", $file)) {
				return true;
			}
		}

		return false;
	}

	function number_of_files_in_dir ($dir) {
		$filecount = count(glob($dir. "*/*"));
		return $filecount;
	}

	function nr_of_annotations ($img) {
		$img = hash("sha256", $img);
		$dir = "annotations/$img/";
		$res = number_of_files_in_dir($dir);
		return $res;
	}

	function shuffle_assoc($my_array) {
		$keys = array_keys($my_array);

		shuffle($keys);

		foreach($keys as $key) {
			$new[$key] = $my_array[$key];
		}

		$my_array = $new;

		return $my_array;
	}

	function get_number_of_annotated_imgs() {
		$files = scandir("images");

		$annotated = 0;
		$not_annotated = 0;

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file)) {
				$file_hash = hash("sha256", $file);
				if(is_dir("annotations/$file_hash/")) {
					$annotated++;
				} else {
					$not_annotated++;
				}
			}
		}

		return [$annotated, $not_annotated];
	}
?>
