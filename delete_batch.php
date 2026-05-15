<?php
	include_once("functions.php");

	header('Content-Type: text/html; charset=utf-8');

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		http_response_code(405);
		die("Method not allowed. Use POST.");
	}

	$input = json_decode(file_get_contents('php://stdin') ?: file_get_contents('php://input'), true);

	if (!$input || !isset($input['annotations']) || !is_array($input['annotations'])) {
		http_response_code(400);
		die("Invalid or missing 'annotations' array in request body.");
	}

	$annotations = $input['annotations'];

	if (count($annotations) === 0) {
		echo "OK Deleted 0 annotations";
		exit(0);
	}

	$annotate_userid = $_COOKIE["annotate_userid"] ?? null;

	if (!$annotate_userid) {
		http_response_code(400);
		die("No user cookie set.");
	}

	if (!preg_match("/^([a-f0-9]{64})$/", $annotate_userid)) {
		http_response_code(403);
		die("Invalid user cookie format.");
	}

	$user_id = get_or_create_user_id($annotate_userid);
	$deleted_count = 0;

	foreach ($annotations as $annotation) {
		if (!isset($annotation['id']) || empty($annotation['id'])) {
			continue;
		}

		$raw_id = $annotation['id'];
		$source = $annotation['source'] ?? null;

		// === Resolve image_id from source for scoped deletion ===
		$image_id = null;
		if ($source) {
			// Clean source: strip print_image.php?filename= prefix and cache busters
			$clean_source = preg_replace("/^.*?print_image\.php\?filename=/", "", $source);
			$clean_source = preg_replace("/&_=.*/", "", $clean_source);
			$clean_source = urldecode($clean_source);
			$image_id = get_image_id($clean_source);
		}

		// === Build candidate base IDs (with and without '#' prefix) ===
		$base_ids = [$raw_id];

		if (strpos($raw_id, '#') === 0) {
			$base_ids[] = substr($raw_id, 1); // strip leading '#'
		} else {
			$base_ids[] = '#' . $raw_id;      // add leading '#'
		}

		// If the raw_id already has a suffix like _1, _2, also try the base without suffix
		if (preg_match('/^(.+)_\d+$/', $raw_id, $m)) {
			$stripped = $m[1];
			$base_ids[] = $stripped;
			if (strpos($stripped, '#') === 0) {
				$base_ids[] = substr($stripped, 1);
			} else {
				$base_ids[] = '#' . $stripped;
			}
		}

		$base_ids = array_unique($base_ids);
		$affected = 0;

		foreach ($base_ids as $candidate_id) {
			$escaped_candidate = my_mysqli_real_escape_string($candidate_id);

			// === FIX: Match exact ID AND suffixed variants (_1, _2, etc.) ===
			// This mirrors the logic in delete_annotation.php and submit.php
			$where_clause = "(
				annotarius_id = " . esc($candidate_id) . "
				OR annotarius_id LIKE '" . $escaped_candidate . "\_%'
			)";

			// Scope to user
			$where_clause .= " AND user_id = " . esc($user_id);

			// Scope to image if we have it
			if ($image_id) {
				$where_clause .= " AND image_id = " . esc($image_id);
			}

			// First check if any rows exist
			$check = rquery("SELECT id FROM annotation WHERE $where_clause LIMIT 1");

			if ($check && mysqli_num_rows($check) > 0) {
				rquery("DELETE FROM annotation WHERE $where_clause");
				$affected = mysqli_affected_rows($GLOBALS['dbh']);
				if ($affected > 0) {
					break; // Found and deleted, move to next annotation
				}
			}
		}

		// === Last resort: try without user_id scoping (in case of mismatch) ===
		if ($affected === 0 && $image_id) {
			foreach ($base_ids as $candidate_id) {
				$escaped_candidate = my_mysqli_real_escape_string($candidate_id);

				$where_clause = "(
					annotarius_id = " . esc($candidate_id) . "
					OR annotarius_id LIKE '" . $escaped_candidate . "\_%'
				) AND image_id = " . esc($image_id);

				$check = rquery("SELECT id FROM annotation WHERE $where_clause LIMIT 1");

				if ($check && mysqli_num_rows($check) > 0) {
					rquery("DELETE FROM annotation WHERE $where_clause");
					$affected = mysqli_affected_rows($GLOBALS['dbh']);
					if ($affected > 0) {
						break;
					}
				}
			}
		}

		// === Numeric ID fallback ===
		if ($affected === 0 && is_numeric($raw_id)) {
			$where_clause = "id = " . intval($raw_id);
			if ($image_id) {
				$where_clause .= " AND image_id = " . esc($image_id);
			}

			$check = rquery("SELECT id FROM annotation WHERE $where_clause LIMIT 1");
			if ($check && mysqli_num_rows($check) > 0) {
				rquery("DELETE FROM annotation WHERE $where_clause");
				$affected = mysqli_affected_rows($GLOBALS['dbh']);
			}
		}

		if ($affected > 0) {
			$deleted_count += $affected;
		}
	}

	echo "OK Deleted $deleted_count annotations";
?>
