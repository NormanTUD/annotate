<?php
	include_once("functions.php");

	if(array_key_exists("source", $_POST)) {
		if(array_key_exists("id", $_POST)) {
			$image_id = get_or_create_image_id($_POST["source"]);
			$annotarius_id = $_POST["id"];
			$user_id = get_or_create_user_id($_COOKIE["annotate_userid"]);

			$parsed_position = parse_position($_POST["position"], get_image_width($image_id), get_image_height($image_id));
			$x_start = $parsed_position[0];
			$y_start = $parsed_position[1];
			$w = $parsed_position[2];
			$h = $parsed_position[3];

			$category_name = $_POST["body"][0]["value"];

			$category_id = get_or_create_category_id($category_name);

			$json = json_encode($_POST);

			$anno_id = create_annotation($image_id, $user_id, $category_id, $x_start, $y_start, $w, $h, $json, $annotarius_id);

			print "Annotation category $category_name for image ".$_POST['source']." ($image_id) saved (anno-id: $anno_id)";
		} else {
			die("No ID given");
		}
	} else {
		die("No source given");
	}

?>
