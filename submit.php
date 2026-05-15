<?php
	include_once("functions.php");

	$used_model = "";

	if (!isset($_POST["source"])) die("No source given");
	if (!isset($_POST["id"])) die("No ID given");
	if (isset($_POST["used_model"])) {
		$used_model = $_POST["used_model"];
	}

	$filename = urldecode(html_entity_decode($_POST["source"]));
	$filename = preg_replace("/print_image\.php\?filename=/", "", $filename);
	$filename = preg_replace("/&_=.*/", "", $filename);

	$image_id = get_or_create_image_id("", $filename);
	if (!$image_id) die("Could not get image id for $filename");

	$user_id = get_or_create_user_id($_COOKIE["annotate_userid"]);
	$base_annotarius_id = $_POST["id"];

	$parsed_position = parse_position($_POST["position"], get_image_width($image_id), get_image_height($image_id));
	if (!$parsed_position) die("Could not parse position");
	list($x_start, $y_start, $w, $h) = $parsed_position;

	if (!isset($_POST["body"]) || !is_array($_POST["body"]) || count($_POST["body"]) === 0)
		die("Label not properly found");

	// --- FIX: Delete ALL old rows that match this base annotarius_id (with or without suffix) ---
	// This ensures that when a user removes a tag (e.g. LSS), the old DB row is gone.
	$base_id_escaped = my_mysqli_real_escape_string($base_annotarius_id);
	rquery("DELETE FROM annotation WHERE image_id = " . esc($image_id) . " AND (
		annotarius_id = " . esc($base_annotarius_id) . "
		OR annotarius_id LIKE '" . $base_id_escaped . "\_%'
	)");

	// Also try without/with '#' prefix
	$alt_base = $base_annotarius_id;
	if (strpos($base_annotarius_id, '#') === 0) {
		$alt_base = substr($base_annotarius_id, 1);
	} else {
		$alt_base = '#' . $base_annotarius_id;
	}
	$alt_base_escaped = my_mysqli_real_escape_string($alt_base);
	rquery("DELETE FROM annotation WHERE image_id = " . esc($image_id) . " AND (
		annotarius_id = " . esc($alt_base) . "
		OR annotarius_id LIKE '" . $alt_base_escaped . "\_%'
	)");

	$saved_categories = [];
	$counter = 1;

	// Decode original 'full' JSON once to reuse as template
	$original_full = null;
	if (isset($_POST['full'])) {
		$decoded = json_decode($_POST['full'], true);
		if (json_last_error() === JSON_ERROR_NONE) $original_full = $decoded;
	}

	// Build the complete body array for the 'full' JSON
	$all_bodies = $_POST["body"];

	foreach ($_POST["body"] as $body_item) {
		if (!isset($body_item["value"])) continue;

		$category_name = trim($body_item["value"]);
		$category_id = get_or_create_category_id($category_name);

		// FIX: Use suffix for DB uniqueness, but store the FULL body list in the JSON
		$annotarius_id = $base_annotarius_id . "_" . $counter;

		// Build per-tag POST-like structure but with ALL bodies in 'full'
		$per_post = $_POST;
		$per_post['body'] = array($body_item); // single body for this row
		$per_post['id'] = $annotarius_id;

		// The 'full' JSON always contains ALL bodies so get_current_annotations can reconstruct
		if ($original_full !== null) {
			$per_full = $original_full;
			$per_full['body'] = $all_bodies; // ALL bodies, not just this one
			$per_full['id'] = $base_annotarius_id; // Use the BASE id (no suffix)
			$per_post['full'] = json_encode($per_full, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		} else {
			$per_full = array(
				'type' => 'Annotation',
				'body' => $all_bodies, // ALL bodies
				'target' => array(
					'source' => isset($_POST['source']) ? $_POST['source'] : null,
					'selector' => array(
						'type' => 'FragmentSelector',
						'conformsTo' => 'http://www.w3.org/TR/media-frags/',
						'value' => isset($_POST['position']) ? $_POST['position'] : null
					)
				),
				'id' => $base_annotarius_id,
				'@context' => 'http://www.w3.org/ns/anno.jsonld'
			);
			$per_post['full'] = json_encode($per_full, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}

		$json_to_store = json_encode($per_post, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		$anno_id = create_annotation(
			$image_id,
			$user_id,
			$category_id,
			$x_start,
			$y_start,
			$w,
			$h,
			$json_to_store,
			$annotarius_id,
			$used_model
		);

		$saved_categories[] = htmlspecialchars($category_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " (anno-id: $anno_id)";
		$counter++;
	}

	if (count($saved_categories) > 0) {
		print "Annotation categories saved for image <i>" . htmlspecialchars($filename) . "</i>: " . implode(", ", $saved_categories);
	} else {
		die("No categories saved");
	}
?>
