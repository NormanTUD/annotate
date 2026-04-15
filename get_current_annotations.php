<?php
	include_once("functions.php");

	$first_other = 0;
	if(array_key_exists("first_other", $_GET)) {
		$first_other = 1;
	}

	if(array_key_exists("source", $_GET)) {
		$query = "select a.json, a.annotarius_id from annotation a left join image i on a.image_id = i.id left join user u on u.id = a.user_id where i.filename = ".esc($_GET["source"])." and u.name = ".esc($_COOKIE["annotate_userid"]).' and a.deleted = 0 and i.deleted = 0';

		$res = rquery($query);

		$raw_annotations = [];

		while ($row = mysqli_fetch_row($res)) {
			$annotarius_id = $row[1];
			$decoded_json = json_decode(stripcslashes($row[0]), true);
			$full = json_decode($decoded_json["full"], true);

			if (!$full) continue;

			$raw_annotations[] = [
				'annotarius_id' => $annotarius_id,
				'full' => $full
			];
		}

		// Group by base annotarius_id (strip the _N suffix)
		$grouped = [];

		foreach ($raw_annotations as $entry) {
			$annotarius_id = $entry['annotarius_id'];
			$full = $entry['full'];

			// Strip trailing _N (e.g., #uuid_1 -> #uuid, #uuid_2 -> #uuid)
			$base_id = preg_replace('/_\d+$/', '', $annotarius_id);

			if (!isset($grouped[$base_id])) {
				// Use the first annotation as the template, but reset its id to the base
				$full['id'] = $base_id;
				$grouped[$base_id] = $full;
			} else {
				// Merge body items from subsequent rows into the existing annotation
				if (isset($full['body']) && is_array($full['body'])) {
					foreach ($full['body'] as $body_item) {
						// Avoid duplicates
						$dominated = false;
						foreach ($grouped[$base_id]['body'] as $existing_body) {
							if (isset($existing_body['value']) && isset($body_item['value']) &&
								$existing_body['value'] === $body_item['value']) {
								$dominated = true;
								break;
							}
						}
						if (!$dominated) {
							$grouped[$base_id]['body'][] = $body_item;
						}
					}
				}
			}
		}

		// Return as a flat array of merged annotations
		$jsons = array_values($grouped);

		print json_encode($jsons, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	} else {
		print("[]");
		exit(0);
	}
?>
