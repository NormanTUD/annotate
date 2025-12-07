<?php
require_once "functions.php";

$filename = $_POST["filename"] ?? null;
$rotation = floatval($_POST["rotation"] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM image WHERE filename = ? LIMIT 1");
$stmt->execute([$filename]);
$image_id = $stmt->fetchColumn();

$stmt = $pdo->prepare("
	INSERT INTO image_rotation (image_id, rotation_deg)
	VALUES (?, ?)
	ON DUPLICATE KEY UPDATE rotation_deg = VALUES(rotation_deg)
");
$stmt->execute([$image_id, $rotation]);
