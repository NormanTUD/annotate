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

	function print_header() {
		$annotation_stat = get_number_of_annotated_imgs();
?>
		<a href='tutorial.mp4' target="_blank">Video-Anleitung</a>, Anzahl annotierter Bilder: <?php print htmlentities($annotation_stat[0] ?? ""); ?>, Anzahl unannotierter Bilder: <?php print htmlentities($annotation_stat[1] ?? ""); ?>
	<?php
			if($annotation_stat[1] != 0) {
				$percent = sprintf("%0.2f", ($annotation_stat[0] / $annotation_stat[1]) * 100);
				print " ($percent%)";
			}
	?>, <a href="overview.php">Übersicht über meine annotierten Bilder</a>
		<br>
<?php
	}

	function get_current_tags () {
		$files = scandir("images");

		$annos = [];

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file) && !preg_match("/^\.(?:\.)?$/", $file)) {
				$file_hash = hash("sha256", $file);
				$base_dir = "annotations/$file_hash/";
				if(is_dir($base_dir)) {
					$users = scandir("annotations/$file_hash/");
					foreach($users as $a_user) {
						if(!preg_match("/^\.(?:\.)?$/", $a_user)) {
							$tdir = "annotations/$file_hash/$a_user/";
							$an = scandir($tdir);
							foreach($an as $a_file) {
								if(!preg_match("/^\.(?:\.)?$/", $a_file)) {
									$path = "$tdir/$a_file";
									$anno = json_decode(file_get_contents($path), true);

									foreach ($anno["body"] as $item) {
										if($item["purpose"] == "tagging") {
											$value = $item["value"];
											if(!array_key_exists($value, $annos)) {
												$annos[$value] = 1;
											} else {
												$annos[$value]++;
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}

		arsort($annos);

		return $annos;
	}
?>
