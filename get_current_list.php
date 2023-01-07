<?php
	include_once("functions.php");

	$json_string = $GLOBALS["memcache"]->get("get_current_list");

	if(!$json_string) {
		$tags = get_current_tags();
		$tags_as_array = [];
		$html = "<ul style='list-style: conic-gradient'>";
		foreach ($tags as $tag => $nr) {
			$html .= "<li><a target='_blank' href='export_annotations.php?format=html&show_categories[]=".htmlentities(urlencode($tag))."'>$tag ($nr)</li>";
		}
		$html .= "</ul>";

		$json_string = json_encode(array("html" => $html, "tags" => $tags));
		$GLOBALS["memcache"]->set("get_current_list", $json_string, 0, 10);
	}

	print($json_string);
?>
