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

		print "Cleaning images...<br>";

		$files_in_db = [];
		$query = "select filename from image";
		$res = rquery($query);
		while ($row = mysqli_fetch_row($res)) {
			$files_in_db[] = $row[0];
		}

		$files = scandir("images");

		shuffle($files);

		$i = 0;
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
					rquery("COMMIT;");
					rquery("SET autocommit=1;");
				}
			}

		}

		print "Done importing";

		exit(0);
	}

	if(file_exists("/etc/cleanup_annotate") || get_get("cleanup")) {
		ini_set('memory_limit', '4096M');
		ini_set('max_execution_time', '300');
		set_time_limit(300);

		function shutdown() {
			mywarn("Exiting, rolling back changes\n");
			rquery("ROLLBACK;");
			rquery("SET autocommit=1;");
		}

		register_shutdown_function('shutdown');

		print "Cleaning images...<br>";

		$files_in_db = [];
		$query = "select id, filename from image where deleted = '0' and offtopic = '0'";
		$res = rquery($query);
		while ($row = mysqli_fetch_row($res)) {
			if(!file_exists("images/".$row[1])) {
				print "File ".$row[1]." (".$row[0].") does not exist anymore<br>";
				rquery("update image set deleted = '1' where id = ".esc($row[0]));
			}
		}

		/*
		$i = 0;
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
					rquery("COMMIT;");
					rquery("SET autocommit=1;");
				}
			}

		}
		 */

		print "Done cleaning";

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
		if(get_get("like")) {
			$imgfile = get_next_random_unannotated_image(get_get("like"));
		} else {
			$imgfile = get_next_random_unannotated_image();
		}
	}

	$number_annotated = get_number_of_annotated_imgs();

	if($imgfile) {
?>
		<br>

		<div id="loader"></div>
<?php
		if($number_annotated > 10000) {
?>
			<span style="font-size: 20px; color: green">Hallo, wer bis hierhin noch durchgehalten hat, bitte sende mir mal eine Email: <a href="mailto:kochnorman@rocketmail.com">kochnorman@rocketmail.com</a>. Ich kann zwar keine Preise vergeben, aber würde mich gern persönlich bedanken.</span>

			<br>
<?php
		}
?>

		<table>
			<tr>
				<td style="vertical-align: baseline;">
					<div id="content" style="padding: 30px;">
						<p>
							<button id="next_img_button" onClick="load_next_random_image()">N&auml;chstes Bild (n)</button>
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
			load_dynamic_content();
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
