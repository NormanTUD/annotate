<?php
	include_once("functions.php");

	$labelsJsonFromDB = null;

	// Hole die neueste "labels.json"-Datei aus der DB
	$query = "SELECT file_contents FROM models WHERE filename = 'labels.json' ORDER BY upload_time DESC LIMIT 1";
	$res = rquery($query);

	$loaded_from_db = 0;

	if ($row = mysqli_fetch_assoc($res)) {
		$contents = $row['file_contents'];
		$json = json_decode($contents, true);
		if (is_array($json)) {
			// Direkt JSON aus der DB ausgeben
			print json_encode($json);
			$loaded_from_db = 1;
		}
	}

	if(!$loaded_from_db) {
		if (file_exists("labels.json")) {
			print file_get_contents("labels.json");
		} else {
			include("export_helper.php");

			$labels = [];
			$categories = [];

			$annotated_image_ids_query = 'SELECT name FROM category ORDER BY id';
			$res = rquery($annotated_image_ids_query);

			while ($row = mysqli_fetch_row($res)) {
				$category = $row[0];
				if (!in_array($category, $categories)) {
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
	}
?>
