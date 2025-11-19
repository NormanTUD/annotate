<?php
	include_once("functions.php");

	$uid = get_get("uid");
	$filename = get_get("filename");

	if ($filename === "model.json") {
		header('Content-Type: application/json');
		header('Content-Disposition: attachment; filename="model.json"');
	}

	print_model_file($uid, $filename);
?>
