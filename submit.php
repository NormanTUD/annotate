<?php
	include_once("functions.php");

	if(array_key_exists("source", $_POST)) {
		if(array_key_exists("id", $_POST)) {
			$image_id = get_or_create_image_id($_POST["source"]);
			$user_id = get_or_create_user_id($_POST["id"]);

			$parsed_position = parse_position($_POST["position"]);
			$x_start = $parsed_position[0];
			$y_start = $parsed_position[1];
			$x_end = $parsed_position[2];
			$y_end = $parsed_position[2];


			$category_id = get_or_create_category_id($_POST["body"][0]["value"]);

			$json = json_encode($_POST);

			create_annotation($image_id, $user_id, $category_id, $x_start, $y_start, $x_end, $y_end, $json);
		} else {
			die("No ID given");
		}
	} else {
		die("No source given");
	}

?>
