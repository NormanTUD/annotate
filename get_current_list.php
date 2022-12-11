<?php
	include_once("functions.php");

	$tags = get_current_tags();
	$tags_as_array = [];
	$html = "<ul style='list-style: conic-gradient'>";
	foreach ($tags as $tag => $nr) {
		$html .= "<li><a target='_blank' href='export_annotations.php?format=html&show_categories[]=".htmlentities(urlencode($tag))."'>$tag ($nr)</li>";
	}
	$html .= "</ul>";

	print json_encode(array("html" => $html, "tags" => $tags));
?>
