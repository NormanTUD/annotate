<?php
include_once("functions.php");

$uid = get_get("uid");
$filename = get_get("filename");

if ($filename === "model.json") {
    ob_start();
    $json = get_model_file($uid, $filename);

    $json = trim($json);
    $data = json_decode($json, true);

    if ($data === null) {
        // falls JSON nicht dekodierbar ist
        echo $json; // einfach original rausgeben, damit TF.js wenigstens was bekommt
        exit;
    }

    if (isset($data['weightsManifest']) && is_array($data['weightsManifest'])) {
        foreach ($data['weightsManifest'] as &$manifest) {
            if (isset($manifest['paths']) && is_array($manifest['paths'])) {
                foreach ($manifest['paths'] as $i => $path) {
                    $manifest['paths'][$i] = "get_model_file.php?uid=" . urlencode($uid) . "&filename=" . urlencode($path);
                }
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($data);
} else {
    print_model_file($uid, $filename);
}
?>
