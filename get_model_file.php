<?php
include_once("functions.php");

$uid = get_get("uid");
$filename = get_get("filename");

if ($filename === "model.json") {
	header('Content-Type: application/json');

	$json = print_model_file($uid, $filename, true); // wir geben hier das JSON als String zurück

	$data = json_decode($json, true);

	if (isset($data['weightsManifest']) && is_array($data['weightsManifest'])) {
		foreach ($data['weightsManifest'] as &$manifest) {
			if (isset($manifest['paths']) && is_array($manifest['paths'])) {
				foreach ($manifest['paths'] as $i => $path) {
					// Ersetze jeden Pfad mit der URL
					$manifest['paths'][$i] = "get_model_file.php?uid=" . urlencode($uid) . "&filename=" . urlencode($path);
				}
			}
		}
	}

	echo json_encode($data);
} else {
	print_model_file($uid, $filename);
}
?>
