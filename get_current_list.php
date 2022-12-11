<?php
	include_once("functions.php");

	$tags = get_current_tags();
	$tags_as_array = [];
	print "<ul style='list-style: conic-gradient'>";
	foreach ($tags as $tag => $nr) {
		print "<li><a target='_blank' href='export_annotations.php?format=html&show_categories[]=".htmlentities(urlencode($tag))."'>$tag ($nr)</li>";
	}
	print "</ul>";
?>
