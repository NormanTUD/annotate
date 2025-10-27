<?php
	include_once("functions.php");

	if(!array_key_exists("source", $_POST)) die("No source given");
	if(!array_key_exists("id", $_POST)) die("No ID given");

	$filename = urldecode(html_entity_decode($_POST["source"]));
	$filename = preg_replace("/print_image.php.filename=/", "", $filename);

	$image_id = get_or_create_image_id("", $filename);
	if(!$image_id) die("Could not get image id for $filename");

	$annotarius_id = $_POST["id"];
	$user_id = get_or_create_user_id($_COOKIE["annotate_userid"]);

	$parsed_position = parse_position($_POST["position"], get_image_width($image_id), get_image_height($image_id));
	$x_start = $parsed_position[0];
	$y_start = $parsed_position[1];
	$w = $parsed_position[2];
	$h = $parsed_position[3];

	if(!isset($_POST["body"]) || !is_array($_POST["body"]) || count($_POST["body"]) === 0) die("Label not properly found");

	$saved_categories = [];
	foreach($_POST["body"] as $body_item) {
		if(!isset($body_item["value"])) continue;
		$category_name = $body_item["value"];
		$category_id = get_or_create_category_id($category_name);
		$json = json_encode($_POST);
		$anno_id = create_annotation($image_id, $user_id, $category_id, $x_start, $y_start, $w, $h, $json, $annotarius_id);
		$saved_categories[] = "<b>$category_name</b>";
	}

	if(count($saved_categories) > 0) {
		print "Annotation category ".implode(", ", $saved_categories)." for image <i>$filename</i> (image-id: $image_id) saved";
	} else {
		die("No categories saved");
	}
?>
