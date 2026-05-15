<?php
include("functions.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed. Use POST."]);
    exit;
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid category ID."]);
    exit;
}

$category_id = (int)$_POST['id'];

// Check if category exists
$result = rquery("SELECT id FROM category WHERE id = $category_id");
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "Category not found."]);
    exit;
}

// Check for annotations referencing this category
$anno_check = rquery("SELECT COUNT(*) as cnt FROM annotation WHERE category_id = $category_id AND deleted = '0'");
$anno_row = mysqli_fetch_assoc($anno_check);
$annotation_count = (int)$anno_row['cnt'];

if ($annotation_count > 0) {
    // Option A: Refuse deletion if annotations exist
    // Uncomment the block below to enforce this behavior:
    /*
    http_response_code(409);
    echo json_encode([
        "error" => "Cannot delete category: $annotation_count annotation(s) still reference it. Delete or reassign them first."
    ]);
    exit;
    */

    // Option B: Soft-delete (flag as deleted) all annotations referencing this category
    rquery("UPDATE annotation SET deleted = '1' WHERE category_id = $category_id");
}

// Also remove from model_labels if applicable
rquery("DELETE FROM model_labels WHERE category_id = $category_id");

// Delete the category
$result = rquery("DELETE FROM category WHERE id = $category_id");
if ($result) {
    echo json_encode([
        "success" => true,
        "message" => "Category deleted.",
        "annotations_affected" => $annotation_count
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to delete category."]);
}
?>
