<?php
	include_once("functions.php");

	$first_other = 0;
	if(array_key_exists("first_other", $_GET)) {
		$first_other = 1;
	}

	if(array_key_exists("source", $_GET)) {
		// FIX: Clean source parameter — strip print_image.php?filename= prefix and cache busters
		$source = $_GET["source"];
		$source = preg_replace("/^.*?print_image\.php\?filename=/", "", $source);
		$source = preg_replace("/&_=.*/", "", $source);
		$source = urldecode($source);

		$query = "select a.json, a.annotarius_id, c.name as category_name, 
			a.x_start, a.y_start, a.w, a.h
			from annotation a 
			left join image i on a.image_id = i.id 
			left join user u on u.id = a.user_id
			left join category c on c.id = a.category_id
			where i.filename = ".esc($source)."
			and a.deleted = 0 and i.deleted = 0";

		if(!$first_other) {
			$query .= " and u.name = ".esc($_COOKIE["annotate_userid"]);
		}

		$res = rquery($query);

		$raw_annotations = [];

		while ($row = mysqli_fetch_assoc($res)) {
			$annotarius_id = $row['annotarius_id'];
			$decoded_json = json_decode(stripcslashes($row['json']), true);
			$full = null;

			if ($decoded_json && isset($decoded_json['full'])) {
				$full = json_decode($decoded_json['full'], true);
			}

			if (!$full) {
				// Fallback: if no valid 'full' JSON, skip
				continue;
			}

			$raw_annotations[] = [
				'annotarius_id' => $annotarius_id,
				'full' => $full,
				'category_name' => $row['category_name'],
				'x_start' => $row['x_start'],
				'y_start' => $row['y_start'],
				'w' => $row['w'],
				'h' => $row['h']
			];
		}

		// --- FIX: Group by BASE annotarius_id (strip _N suffix) AND by position ---
		// This ensures that multiple DB rows for the same bounding box
		// (one per category tag) are merged into a single annotation with multiple body tags.

		function get_base_annotarius_id($annotarius_id) {
			// Strip trailing _N suffix added by submit.php
			if (preg_match('/^(.+)_\d+$/', $annotarius_id, $m)) {
				return $m[1];
			}
			return $annotarius_id;
		}

		// Group by position string (most reliable) to handle any ID inconsistencies
		$by_position = [];

		foreach ($raw_annotations as $entry) {
			$full = $entry['full'];
			$base_id = get_base_annotarius_id($entry['annotarius_id']);

			// Determine position key from the full JSON or from DB columns
			$pos_key = null;
			if (isset($full['target']['selector']['value'])) {
				$pos_key = $full['target']['selector']['value'];
			} else {
				// Fallback: construct from DB columns
				$pos_key = "xywh=pixel:" . $entry['x_start'] . "," . $entry['y_start'] . "," . $entry['w'] . "," . $entry['h'];
			}

			// Composite key: base_id + position (handles edge case of same position but different annotations)
			$group_key = $base_id . '||' . $pos_key;

			if (!isset($by_position[$group_key])) {
				// Use the full JSON as the base, but set the ID to the base (no suffix)
				$full['id'] = $base_id;

				// Ensure body is an array
				if (!isset($full['body']) || !is_array($full['body'])) {
					$full['body'] = [];
				}

				// If the full JSON body doesn't contain this row's category, add it
				$has_category = false;
				foreach ($full['body'] as $b) {
					if (isset($b['value']) && $b['value'] === $entry['category_name']) {
						$has_category = true;
						break;
					}
				}
				if (!$has_category && $entry['category_name']) {
					$full['body'][] = [
						'type' => 'TextualBody',
						'value' => $entry['category_name'],
						'purpose' => 'tagging'
					];
				}

				$by_position[$group_key] = $full;
			} else {
				// Merge: add this row's category to the existing body if not already present
				$category = $entry['category_name'];
				if ($category) {
					$exists = false;
					foreach ($by_position[$group_key]['body'] as $existing) {
						if (isset($existing['value']) && $existing['value'] === $category) {
							$exists = true;
							break;
						}
					}
					if (!$exists) {
						$by_position[$group_key]['body'][] = [
							'type' => 'TextualBody',
							'value' => $category,
							'purpose' => 'tagging'
						];
					}
				}
			}
		}

		// Final deduplication: if two different base_ids have the exact same position,
		// merge them (handles legacy data where IDs changed)
		$final_by_pos = [];
		foreach ($by_position as $annotation) {
			$pos = isset($annotation['target']['selector']['value'])
				? $annotation['target']['selector']['value']
				: null;

			if ($pos === null) {
				$final_by_pos[] = $annotation;
				continue;
			}

			if (!isset($final_by_pos[$pos])) {
				$final_by_pos[$pos] = $annotation;
			} else {
				// Merge bodies
				if (isset($annotation['body']) && is_array($annotation['body'])) {
					foreach ($annotation['body'] as $body_item) {
						$exists = false;
						foreach ($final_by_pos[$pos]['body'] as $existing) {
							if (isset($existing['value']) && isset($body_item['value']) &&
								$existing['value'] === $body_item['value']) {
								$exists = true;
								break;
							}
						}
						if (!$exists) {
							$final_by_pos[$pos]['body'][] = $body_item;
						}
					}
				}
			}
		}

		$jsons = array_values($final_by_pos);

		print json_encode($jsons, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	} else {
		print("[]");
		exit(0);
	}
?>
