<?php
	include_once("functions.php");

	$only_uncurated = "";
	$delete_on_click = "";
	if(isset($_GET["only_uncurated"])) {
		$only_uncurated = "&only_uncurated=1";
	}

	if(isset($_GET["delete_on_click"])) {
		$delete_on_click = "&delete_on_click=1";
	}

	$tags = get_current_tags($only_uncurated ?? 0);
	$tags_as_array = [];
	$html = "<ul style='list-style: conic-gradient'>";
	foreach ($tags as $tag => $nr) {
		$html .= "<li><a target='_blank' href='export_annotations.php?format=html&show_categories[]=".htmlentities(urlencode($tag))."$only_uncurated$delete_on_click'>$tag ($nr)</li>";
	}
	$html .= "</ul>";

	$json_string = json_encode(array("html" => $html, "tags" => $tags));

	print($json_string);
?>
