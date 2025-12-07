<?php
require_once "functions.php";

$filename = $_GET["filename"] ?? null;
if (!$filename) die(json_encode(["error" => "no filename"]));

$stmt = $GLOBALS["pdo"]->prepare("
	SELECT r.rotation_deg 
	FROM image_rotation r
	JOIN image i ON i.id = r.image_id
	WHERE i.filename = ?
	LIMIT 1
");
$stmt->execute([$filename]);
$rot = $stmt->fetchColumn();

echo json_encode([
	"filename" => $filename,
	"rotation" => $rot !== false ? floatval($rot) : 0
]);
