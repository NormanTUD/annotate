<?php
		include("header.php");
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
			if(number_of_annotations($user_id, $f)) {
?>
				<img class="images" id="<?php print uniqid(); ?>" src="images/<?php print $f; ?>">
<?php
			}
		}
?>
	</div>
	<script>
		create_annos();
	</script>
</body>
