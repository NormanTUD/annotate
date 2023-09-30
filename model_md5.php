<?php
	include_once("functions.php");

	if(file_exists("model.json")) {
		$json_str = file_get_contents("model.json");
		try {
			$json = json_decode($json_str, 1);

			$paths = $json["weightsManifest"][0]["paths"];

			$md5s = array(
				hash('md5', file_get_contents("model.json"))
			);

			foreach ($paths as $path) {
				if(!file_exists($path)) {
					exit(0);
				}

				$md5s[] = hash('md5', file_get_contents($path));
			}

			$joined_md5s = join(", ", $md5s);

			$joined_md5s_hash = hash('md5', $joined_md5s);

			print $joined_md5s_hash;
		} catch (\Throwable $e) {
			dier($e);
		}
	}
?>
