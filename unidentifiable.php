<?php
		include_once("header.php");
		include_once("functions.php");

		$files = scandir("unidentifiable");

		$img_files = array();

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file)) {
				$annotations = number_of_annotations($user_id, $file);
				$img_files[$file] = $annotations;
			}
		}

		asort($img_files);
		#print_header();
?>
	<div id="content">
<?php
		foreach ($img_files as $f => $k) {
?>
			<a target="_blank" href="index.php?edit=<?php print $f; ?>&move_from_unidentifiable=<?php print $f; ?>"><img class="images" id="<?php print uniqid(); ?>" src="unidentifiable/<?php print $f; ?>"></a>
<?php
		}
?>
	</div>
<?php include_once("footer.php"); ?>
