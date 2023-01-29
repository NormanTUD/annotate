<?php
	include("header.php");
	include_once("functions.php");

	if(file_exists("/etc/import_annotate") || get_get("import")) {
		ini_set('memory_limit', '4096M');
		ini_set('max_execution_time', '300');
		set_time_limit(300);

		function shutdown() {
			mywarn("Exiting, rolling back changes\n");
			rquery("ROLLBACK;");
			rquery("SET autocommit=1;");
		}

		register_shutdown_function('shutdown');

		print "Importing images...<br>";

		$files_in_db = [];
		$query = "select filename from image";
		$res = rquery($query);
		while ($row = mysqli_fetch_row($res)) {
			$files_in_db[] = $row[0];
		}

		$files = scandir("images");

		shuffle($files);

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file) && !in_array($file, $files_in_db)) {
				$new = is_null(get_image_id($file)) ? 1 : 0;
				if($new) {
					rquery("SET autocommit=0;");
					rquery("START TRANSACTION;");
					$image_id = get_or_create_image_id($file);
					print "Id for $file: ".$image_id."<br>\n";
					ob_flush();
					flush();


					$file_hash = hash("sha256", $file);
					$anno_path = "annotations/".$file_hash;

					if(file_exists($anno_path)) {
						$user_dir = scandir($anno_path);
						foreach($user_dir as $user) {
							if(preg_match("/^[\w\d]+$/", $user)) {
								$user_id = get_or_create_user_id($user);
								print "Created user $user ($user_id)<br>\n";


								$annotations = scandir("$anno_path/$user");

								foreach ($annotations as $this_anno) {
									if(preg_match("/\.json$/", $this_anno)) {
										$this_anno_file = "$anno_path/$user/$this_anno";

										$json_file = file_get_contents($this_anno_file);
										$json = json_decode($json_file, TRUE);

										$category_id = get_or_create_category_id($json["body"][0]["value"]);

										$parsed_position = parse_position($json["position"], get_image_width($image_id), get_image_height($image_id));
										if(is_null($parsed_position)) {
											dier($json);
										} else {
											$x_start = $parsed_position[0];
											$y_start = $parsed_position[1];
											$w = $parsed_position[2];
											$h = $parsed_position[3];
										}

										$annotarius_id = $json["id"];

										create_annotation($image_id, $user_id, $category_id, $x_start, $y_start, $w, $h, $json_file, $annotarius_id);
									}
								}
							}
						}
					} else {
						// dont import
					}
					rquery("COMMIT;");
					rquery("SET autocommit=1;");
				}
			}
		}

		print "Done importing";

		exit(0);
	}

	if(array_key_exists("move_from_identifiable", $_GET)) {
		if(!preg_match("/\.\./", $_GET["move_from_identifiable"]) && preg_match("/\.jpg/", $_GET["move_from_identifiable"])) {
			$f = "identifiable/".$_GET["move_from_identifiable"];
			$t = "images/".$_GET["move_from_identifiable"];
			if(file_exists($f)) {
				if(!file_exists($t)) {
					rename($f, $t);
				} else {
					mywarn("$f wurde gefunden, aber $t exitiert bereits");
				}
			}
		}
	}

	if(array_key_exists("move_from_offtopic", $_GET)) {
		if(!preg_match("/\.\./", $_GET["move_from_offtopic"]) && preg_match("/\.jpg/", $_GET["move_from_offtopic"])) {
			$f = "offtopic/".$_GET["move_from_offtopic"];
			$t = "images/".$_GET["move_from_offtopic"];
			if(file_exists($f)) {
				if(!file_exists($t)) {
					rename($f, $t);
				} else {
					mywarn("$f wurde gefunden, aber $t exitiert bereits");
				}
			}
		}
	}

	if(array_key_exists("move_to_unidentifiable", $_GET)) {
		rquery("update image set unidentifiable = 1 where filename = ".esc($_GET["move_to_unidentifiable"]));
		rquery("update image set deleted = 1 where filename = ".esc($_GET["move_to_unidentifiable"]));
		if(!preg_match("/\.\./", $_GET["move_to_unidentifiable"]) && preg_match("/\.jpg/", $_GET["move_to_unidentifiable"])) {
			$f = "images/".$_GET["move_to_unidentifiable"];
			$t = "unidentifiable/".$_GET["move_to_unidentifiable"];
			if(file_exists($f)) {
				if(!file_exists($t)) {
					rename($f, $t);
				} else {
					mywarn("$f wurde gefunden, aber $t exitiert bereits");
				}
			}
		}
	}

	if(array_key_exists("move_to_offtopic", $_GET)) {
		rquery("update image set offtopic = 1 where filename = ".esc($_GET["move_to_offtopic"]));
		rquery("update image set deleted = 1 where filename = ".esc($_GET["move_to_offtopic"]));
		if(!preg_match("/\.\./", $_GET["move_to_offtopic"]) && preg_match("/\.jpg/", $_GET["move_to_offtopic"])) {
			$f = "images/".$_GET["move_to_offtopic"];
			$t = "offtopic/".$_GET["move_to_offtopic"];
			if(file_exists($f)) {
				if(!file_exists($t)) {
					rename($f, $t);
				} else {
					mywarn("$f wurde gefunden, aber $t exitiert bereits");
				}
			}
		}
	}

	$imgfile = "";
	if(isset($_GET["edit"])) {
		$imgfile = $_GET["edit"];
	} else {
		$imgfile = get_next_random_unannotated_image();
	}

	dier("imgfile:>$imgfile<");

	if($imgfile) {
?>
		<br>

		<div id="loader"></div>

		<span style="font-size: 20px; color: red">BITTE KEINE NEUEN KATEGORIEN EINFÜGEN</span>

		<table>
			<tr>
				<td style="vertical-align: baseline;">
					<div id="content" style="padding: 30px;">
						<p>
							<button onClick="load_next_random_image()">N&auml;chstes Bild (n)</button>
							<button><a onclick="ai_file($('#image')[0])">KI-Labelling (k)</a></button>
							<button onclick="move_to_offtopic()">Bild ist Off Topic (o)</button>
							<button onclick="move_to_unidentifiable()">Bild ist nicht identifizierbar (u)</button>
						</p>
						<div id="ki_detected_names"></div>
						<img id="image" />
						<br>
						<div id="filename"></div>
					</div>
				</td>
				<td>
					Aktuelle Tags:

					<div id="list"></div>
				</td>
			</tr>
		</table>

		<script>
			load_next_random_image("<?php print htmlentities($imgfile); ?>");
		</script>
<?php
	} else {
?>
			Aktuell sind alle vorhandenen Bilder annotiert. Bitte checken Sie die Seite später erneut.
<?php
	}
	include_once("footer.php");
?>
