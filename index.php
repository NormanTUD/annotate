<?php
	include("header.php");
	include_once("functions.php");

	if(file_exists("/etc/import_annotate") || get_get("import")) {
		import_files();
	}

	if(file_exists("/etc/cleanup_annotate") || get_get("cleanup")) {
		cleanup();
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
		<table>
			<tr>
				<td style="vertical-align: baseline;">
					<div id="content" style="padding: 30px;">
						<p>
							<button id="next_img_button" onClick="load_next_random_image()">N&auml;chstes Bild (n)</button>
							<button class='ai_stuff' id="autonext_img_button" onClick="autonext()">AutoNext</button>
							<button class='ai_stuff'><a onclick="ai_file($('#image')[0])">KI-Labelling (k)</a></button>
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
