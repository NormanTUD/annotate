<?php
	include_once("functions.php");

	$first_other = 0;
	if(array_key_exists("first_other", $_GET)) {
		$first_other = 1;
	}

	if(array_key_exists("source", $_GET)) {
		$query = "select a.json from annotation a left join image i on a.image_id = i.id left join user u on u.id = a.user_id where i.filename = ".esc($_GET["source"])." and u.name  = ".esc($_COOKIE["annotate_userid"]).' and deleted = 0 order by a.modified desc limit 1';

		$res = rquery($query);

		$json = [];

		while ($row = mysqli_fetch_row($res)) {
			$json = json_decode(stripcslashes($row[0]), TRUE)["full"];
		}

		print $json;
	} else {
		print("[]");
		exit(0);
	}
?>
