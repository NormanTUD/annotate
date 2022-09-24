<?php
		include_once("header.php");
		include_once("functions.php");

		$files = scandir("images");

		$img_files = array();

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file)) {
				$annotations = number_of_annotations($user_id, $file);
				$img_files[$file] = $annotations;
			}
		}

		asort($img_files);
		print_header();
?>
	<div id="content">
<?php
		foreach ($img_files as $f => $k) {
?>
			<!--<a target="_blank" href="index.php?move_to_offtopic=<?php print $f; ?>"><img class="images" id="<?php print uniqid(); ?>" src="images/<?php print $f; ?>"></a>-->
			<a target="_blank" href="index.php?edit=<?php print $f; ?>"><img class="images" id="<?php print uniqid(); ?>" src="images/<?php print $f; ?>"></a>
<?php
		}
?>
	</div>
</body>
