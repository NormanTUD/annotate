<?php
	include_once("functions.php");

	$uid = get_get("uid");
	$filename = get_get("filename");

	print_model_file($uid, $filename);
?>
