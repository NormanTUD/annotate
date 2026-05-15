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

    // Resolve the image from the source parameter to scope deletion
    $image_id = get_image_id($source);
    if (!$image_id) {
        http_response_code(404);
        echo json_encode(["error" => "Image not found for source: " . $source]);
        exit;
    }

    // Build candidate annotarius_ids to try
    $candidates = [$raw_id];

    if (strpos($raw_id, '#') === 0) {
        $candidates[] = substr($raw_id, 1); // strip leading '#'
    } else {
        $candidates[] = '#' . $raw_id;      // add leading '#'
    }

    $affected = 0;

    foreach ($candidates as $candidate_id) {
        // Verify ownership: annotation must belong to this user AND this image
        $check = rquery(
            "SELECT id FROM annotation WHERE annotarius_id = " . esc($candidate_id) .
            " AND user_id = " . esc($user_id) .
            " AND image_id = " . esc($image_id)
        );

        if ($check && mysqli_num_rows($check) > 0) {
            flag_deleted($candidate_id);
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
