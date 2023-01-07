<?php
	include_once("functions.php");

	$home_string = $GLOBALS["memcache"]->get("get_home_string");

	if(!$home_string) {
		$home_string = get_home_string();
		$GLOBALS["memcache"]->set("get_home_string", $home_string, 0, 10);
	}

	print $home_string;
?>
