<?php
		include("header.php");
		include_once("functions.php");

		$files = scandir("images");

		$img_files = array();

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file)) {
				$annotations = number_of_annotations_total($file);
				$img_files[$file] = $annotations;
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
				} else {
					mywarn("$f wurde nicht gefunden");
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
				} else {
					mywarn("$f wurde nicht gefunden");
				}
			}
		}

		$img_files = shuffle_assoc($img_files);
		asort($img_files);

		$j = 0;
		$imgfile = "";
		foreach ($img_files as $f => $k) {
			if($j != 0) {
				continue;
			}
			$imgfile = $f;
			$j++;
		}

		if(array_key_exists("edit", $_GET)) {
			$imgfile = $_GET["edit"];
		} else {
			header('Location:'.$_SERVER['PHP_SELF'].'?edit='.urlencode($imgfile));
			exit(0);
		}

		if(!$imgfile) {
			die("Cannot find an image");
		}

		if(!file_exists("images/$imgfile")) {
			print("Cannot find given image");
			header('Location:'.$_SERVER['PHP_SELF']);
			exit();
		}

		#print_header();
?>
	<br>
	<table>
		<tr>
			<td style="vertical-align: baseline;">
				<div id="content" style="padding: 30px;">
					<p><button onClick="next_img()">N&auml;chstes Bild</button><br></p>
					<p><button><a href="index.php?move_to_offtopic=<?php print $imgfile; ?>">Bild ist Off Topic</a></button><br></p>
					<img id="image" src="images/<?php print $imgfile; ?>">
					<br><?php print $imgfile; ?>
				</div>
			</td>
			<td>
				Aktuelle Tags (anklicken für Beispieldaten):
<?php
				$tags = get_current_tags();
				$tags_as_array = [];
				print "<ul>";
				foreach ($tags as $tag => $nr) {
					print "<li><a target='_blank' href='categories.php?searchtag=".htmlentities(urlencode($tag))."'>$tag ($nr)</li>";
					$tags[] = $tag;
				}
				print "</ul>";


?>
			</td>
		</tr>
	</table>

	<script>

		make_item_anno($("#image")[0], [
			{
				widget: 'TAG', vocabulary: [ <?php print '"'.join('", "', $tags).'"'; ?> ]
			}
		]);
	</script>
<?php include_once("footer.php"); ?>
