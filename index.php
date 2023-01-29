<?php
die("Die Seite wird gerade gewartet");
	include("header.php");
	include_once("functions.php");

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
	}
?>
	<br>

	<div id="loader"></div>

	<span style="font-size: 20px; color: red">BITTE KEINE NEUEN KATEGORIEN EINFÜGEN</span>
	<br>
	<span style="font-size: 15px; color: green">Nachricht vom Admin: Ich weiß, dass die Seite langsam geworden ist; das liegt daran, dass ich nie gedacht hätte dass wir SO viele Bilder zusammenkriegen, dafür habe ich die Software anfangs nicht ausgelegt! Ich arbeite bereits an Lösungen, die die Seite wieder annehmbar schnell machen. Vielen Dank für den Support!</span>

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
	include_once("footer.php");
?>
