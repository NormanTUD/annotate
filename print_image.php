<?php
	include_once("functions.php");
	
	$filename = get_get("filename");

	if($filename == "") {
		print("Undefined filename");
		exit(1);
	}

	$filename = html_entity_decode($filename);

	print_image($filename);
?>
