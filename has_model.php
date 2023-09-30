<?php
	include_once("functions.php");

	if(file_exists("model.json")) {
		$json_str = file_get_contents("model.json");
		try {
			$json = json_decode($json_str, 1);

			$paths = $json["weightsManifest"][0]["paths"];

			foreach ($paths as $path) {
				if(!file_exists($path)) {
					print 0;
					exit(0);
				}
			}
			print 1;
		} catch (\Throwable $e) {
			print $e;
			print "0";
		}
	} else {
		print 0;
	}
?>
