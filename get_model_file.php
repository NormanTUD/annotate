<?php
include_once("functions.php");

$uid = get_get("uid");
$filename = get_get("filename");

if ($filename === "model.json") {
    header('Content-Type: application/json');

    // Datei direkt laden
    $file_path = __DIR__ . "/models/$uid/$filename"; // anpassen je nach Ablage
    if (!file_exists($file_path)) {
        http_response_code(404);
        echo json_encode(["error" => "File not found"]);
        exit;
    }

    $json = file_get_contents($file_path);
    $data = json_decode($json, true);

    if (isset($data['weightsManifest']) && is_array($data['weightsManifest'])) {
        foreach ($data['weightsManifest'] as &$manifest) {
            if (isset($manifest['paths']) && is_array($manifest['paths'])) {
                foreach ($manifest['paths'] as $i => $path) {
                    $manifest['paths'][$i] = "get_model_file.php?uid=" . urlencode($uid) . "&filename=" . urlencode($path);
                }
            }
        }
    }

    echo json_encode($data);
} else {
    // normale Shard-Dateien ausgeben
    print_model_file($uid, $filename);
}
?>
