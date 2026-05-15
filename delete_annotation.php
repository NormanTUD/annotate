<?php
    include_once("functions.php");

    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed. Use POST."]);
        exit;
    }

    if (!array_key_exists("source", $_POST) || !array_key_exists("id", $_POST)) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required parameters: 'source' and 'id'."]);
        exit;
    }

    $annotate_userid = $_COOKIE["annotate_userid"] ?? null;

    if (!$annotate_userid || !preg_match("/^([a-f0-9]{64})$/", $annotate_userid)) {
        http_response_code(403);
        echo json_encode(["error" => "No valid user cookie set."]);
        exit;
    }

    $user_id = get_or_create_user_id($annotate_userid);
    $raw_id = $_POST["id"];
    $source = $_POST["source"];

    // Clean source: strip print_image.php?filename= prefix and cache busters
    $source = preg_replace("/^.*?print_image\.php\?filename=/", "", $source);
    $source = preg_replace("/&_=.*/", "", $source);
    $source = urldecode($source);

    // Resolve the image from the source parameter to scope deletion
    $image_id = get_image_id($source);
    if (!$image_id) {
        http_response_code(404);
        echo json_encode(["error" => "Image not found for source: " . $source]);
        exit;
    }

    // --- FIX: Build candidate base IDs (with and without '#' prefix) ---
    $base_ids = [$raw_id];

    if (strpos($raw_id, '#') === 0) {
        $base_ids[] = substr($raw_id, 1); // strip leading '#'
    } else {
        $base_ids[] = '#' . $raw_id;      // add leading '#'
    }

    // If the raw_id already has a suffix like _1, _2, also try the base without suffix
    foreach ([$raw_id] as $rid) {
        if (preg_match('/^(.+)_\d+$/', $rid, $m)) {
            $stripped = $m[1];
            $base_ids[] = $stripped;
            if (strpos($stripped, '#') === 0) {
                $base_ids[] = substr($stripped, 1);
            } else {
                $base_ids[] = '#' . $stripped;
            }
        }
    }

    $base_ids = array_unique($base_ids);
    $affected = 0;

    foreach ($base_ids as $candidate_id) {
        $escaped_candidate = my_mysqli_real_escape_string($candidate_id);

        // FIX: Delete exact match AND all suffix variants (_1, _2, etc.)
        // First check ownership on at least one matching row
        $check = rquery(
            "SELECT id FROM annotation WHERE (
                annotarius_id = " . esc($candidate_id) . "
                OR annotarius_id LIKE '" . $escaped_candidate . "\_%'
            )
            AND user_id = " . esc($user_id) . "
            AND image_id = " . esc($image_id) . "
            LIMIT 1"
        );

        if ($check && mysqli_num_rows($check) > 0) {
            rquery(
                "DELETE FROM annotation WHERE (
                    annotarius_id = " . esc($candidate_id) . "
                    OR annotarius_id LIKE '" . $escaped_candidate . "\_%'
                )
                AND user_id = " . esc($user_id) . "
                AND image_id = " . esc($image_id)
            );
            $affected = mysqli_affected_rows($GLOBALS['dbh']);
            if ($affected > 0) {
                break;
            }
        }
    }

    // Try numeric DB primary key as last resort — still with ownership + image check
    if ($affected === 0 && is_numeric($raw_id)) {
        $check = rquery(
            "SELECT id FROM annotation WHERE id = " . intval($raw_id) .
            " AND user_id = " . esc($user_id) .
            " AND image_id = " . esc($image_id)
        );

        if ($check && mysqli_num_rows($check) > 0) {
            rquery(
                "DELETE FROM annotation WHERE id = " . intval($raw_id) .
                " AND user_id = " . esc($user_id) .
                " AND image_id = " . esc($image_id)
            );
            $affected = mysqli_affected_rows($GLOBALS['dbh']);
        }
    }

    echo json_encode([
        "success" => $affected > 0,
        "rows_affected" => $affected,
        "raw_id" => $raw_id
    ]);
?>
