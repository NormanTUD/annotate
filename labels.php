<?php
	include_once("functions.php");

	$model_uuid = $_GET['model_uuid'] ?? null;

	$labels_from_db = [];
	$loaded_from_db = 0;

	if ($model_uuid) {
		// 1. Labels aus der neuen Tabelle holen
		$query = "SELECT label_name FROM model_labels WHERE uid = '" . mysqli_real_escape_string($GLOBALS['mysqli'], $model_uuid) . "' ORDER BY label_index ASC";
		$res = rquery($query);

		while ($row = mysqli_fetch_assoc($res)) {
			$labels_from_db[] = $row['label_name'];
		}

		if (!empty($labels_from_db)) {
			$loaded_from_db = 1;
		}
	}

	$labels = [];

	// 2. Alte Methode als Fallback
	if (!$loaded_from_db) {
		$query = "SELECT file_contents FROM models WHERE filename = 'labels.json' ORDER BY upload_time DESC LIMIT 1";
		$res = rquery($query);

		if ($row = mysqli_fetch_assoc($res)) {
			$contents = $row['file_contents'];
			$json = json_decode($contents, true);
			if (is_array($json)) {
				print json_encode($json);
				exit;
			}
		}

		// Falls keine labels.json vorhanden, altes category-System
		include("export_helper.php");

		$categories = [];
		$res = rquery('SELECT name FROM category ORDER BY id');
		while ($row = mysqli_fetch_row($res)) {
			if (!in_array($row[0], $categories)) {
				$categories[] = $row[0];
			}
		}

		foreach ($categories as $i => $cat) {
			$labels[] = $cat;
		}
	} else {
		// 3. Alte Labels ergänzen, falls sie nicht schon in model_labels sind
		include("export_helper.php");

		$categories = [];
		$res = rquery('SELECT name FROM category ORDER BY id');
		while ($row = mysqli_fetch_row($res)) {
			$categories[] = $row[0];
		}

		foreach ($categories as $cat) {
			if (!in_array($cat, $labels_from_db)) {
				$labels_from_db[] = $cat;
			}
		}

		$labels = $labels_from_db;
	}

	// 4. JSON ausgeben
	print json_encode($labels);
?>
