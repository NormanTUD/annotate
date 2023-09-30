<?php
	include_once("functions.php");
	
	$filename = get_get("filename");

	if($filename == "") {
		print("Undefined filename");
		exit(1);
	}

	print_image($filename);
?>
