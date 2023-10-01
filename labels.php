<?php
	if(file_exists("labels.json")) {
		print file_get_contents("labels.json");
	} else {
		include_once("functions.php");
		include("export_helper.php");

		$labels = [];

		$categories = [];

		$annotated_image_ids_query = get_annotated_image_ids_query();
		$res = rquery($annotated_image_ids_query);

		while ($row = mysqli_fetch_row($res)) {
			$category = $row[3];
			if(!in_array($category, $categories)) {
				$categories[] = $category;
			}
		}

		$category_numbers = array();
		$j = 0;
		foreach ($categories as $i => $cat) {
			$category_numbers[$cat] = $j;
			$labels[] = $cat;
			$j++;
		}

		print json_encode($labels);
	}
?>
