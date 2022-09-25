<?php
	include_once("functions.php");

	function parse_position_rel ($pos, $w, $h) {
		//xywh=pixel:579,354,58,41
		$res = null;
		if(preg_match("/^xywh=pixel:\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)\s*$/", $pos, $matches)) {
			$res["x_0"] = $matches[1] / $w;
			$res["y_0"] = $matches[2] / $h;
			$res["x_1"] = $matches[3] / $w;
			$res["y_1"] = $matches[4] / $h;
		}

		return $res;
	}
	function parse_position_xyxy ($pos) {
		//xywh=pixel:579,354,58,41
		$res = null;
		if(preg_match("/^xywh=pixel:\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)\s*$/", $pos, $matches)) {
			$res["x_0"] = $matches[1];
			$res["y_0"] = $matches[2];
			$res["x_1"] = $matches[3] + $matches[1];
			$res["y_1"] = $matches[4] + $matches[2];
		}

		return $res;
	}

	function parse_position_xywh ($pos) {
		//xywh=pixel:579,354,58,41
		$res = null;
		if(preg_match("/^xywh=pixel:\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)\s*$/", $pos, $matches)) {
			$res["x"] = $matches[1];
			$res["y"] = $matches[2];
			$res["w"] = $matches[3];
			$res["h"] = $matches[4];
		}

		return $res;
	}

	$tmp_name = generateRandomString(20);
	$tmp_dir = "tmp/$tmp_name";
	while (is_dir($tmp_dir)) {
		$tmp_name = generateRandomString(20);
		$tmp_dir = "tmp/$tmp_name";
	}

	ob_start();
	system("mkdir -p $tmp_dir");
	ob_clean();

	$show_categories = array();

	if(isset($_GET["show_categories"])) {
		$show_categories = $_GET["show_categories"];
	}
	#dier(in_array("rsdasdaketenspirale", $show_categories));

	if(is_dir($tmp_dir)) {
		ini_set('memory_limit', '-1');
		$files = scandir("images");
		$images = array();

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file)) {
				$imgsz = getimagesize("images/$file");
				$hash = hash("sha256", $file);
				$dir = "annotations/$hash";

				if(is_dir($dir)) {
					$images[$file] = array(
						"fn" => $file, 
						"hash" => $hash,
						"dir" => $dir,
						"w" => $imgsz[0],
						"h" => $imgsz[1],
						"disabled" => false
					);
				}
			}
		}

		$categories = array();
		$annos = array();

		$k = 0;
		foreach($images as $item) {
			/*
			if($k > 4) {
				continue;
			}
			 */
			if(is_dir($item["dir"])) {
				$user_annotations = scandir($item["dir"]);
				foreach($user_annotations as $user_annotation_dir) {
					if(!preg_match("/^\.\.?$/", $user_annotation_dir)) {
						$tdir = $item["dir"]."/$user_annotation_dir";
						$single_user_annotations = scandir($tdir);
						foreach($single_user_annotations as $single_user_annotation_file) {
							if(preg_match("/\.json$/", $single_user_annotation_file)) {
								#print "<pre>$single_user_annotation_file</pre>";
								$struct = json_decode(file_get_contents("$tdir/$single_user_annotation_file"), true);
								#dier($struct);
								$file = $struct["source"];
								//mywarn($file."\n");
								#dier($images[$file]);

								$has_valid_category = false;
								if(!count($show_categories)) {
									$has_valid_category = true;
								}

								$images[$file]["position_rel"][] = parse_position_rel($struct["position"], $images[$file]["w"], $images[$file]["h"]);
								$images[$file]["position_xywh"][] = parse_position_xywh($struct["position"]);
								$images[$file]["position_xyxy"][] = parse_position_xyxy($struct["position"]);
								foreach ($struct["body"] as $anno) {
									if($anno["purpose"] == "tagging") {
										$anno["value"] = strtolower($anno["value"]);
										if(!in_array($anno["value"], $categories)) {
											$categories[] = $anno["value"];
										}
										$index = array_search($anno["value"], $categories);
										$images[$file]["tags"][] = $index;
										$images[$file]["anno_name"][] = $anno["value"];

										#print $anno["value"];
										#print "<br>\n";
										#print_r($show_categories);
										#print "<br>\n";
										if(!$has_valid_category && in_array($anno["value"], $show_categories)) {
											$has_valid_category = true;
										}
										//dier($index);
										//dier($anno["value"]);
									}
								}

								if(!$has_valid_category) {
									#print "no valid category $file<br><span style='color: red'>disabling entry for $file</span><br>\n";
									$images[$file]["disabled"] = true;
								}

								#print("===>><pre>".print_r($images[$file], true)."</pre><<==");
							}
						}
					}
				}
			} else {
				/*
				print($dir);
				dier($images[$file]);
				die("$dir");
				 */
			}

			//dier($item);
			$k++;
		}

		//dier($images["002215398dcba50ac5d89290c27301c1.jpg"]);
		//dier($images);


		/*
		path: ../datasets/coco128  # dataset root dir
		train: images/train2017  # train images (relative to 'path') 128 images
		val: images/train2017  # val images (relative to 'path') 128 images
		test:  # test images (optional)

		# Classes (80 COCO classes)
		names:
		  0: person
		  1: bicycle
		  2: car
		  ...
		  77: teddy bear
		  78: hair drier
		  79: toothbrush
		*/

		$dataset_yaml = "path: ./\ntrain: images/\nval: images/\nnames:\n";
		foreach ($categories as $i => $cat) {
			$dataset_yaml .= "  $i: $cat\n";
		}

		ob_start();
		mkdir("$tmp_dir/labels/");
		mkdir("$tmp_dir/images/");
		ob_clean();

		file_put_contents("$tmp_dir/dataset.yaml", $dataset_yaml);

		//dier($dataset_yaml);

		// <object-class> <x> <y> <width> <height>
		foreach ($images as $img) {
			if($img["disabled"]) {
				continue;
			}

			$fn = $img["fn"];
			//dier($img);
			#print "<br>$fn<br>";
			$fn_txt = preg_replace("/\.(?:jpe?g|png)$/", ".txt", $fn);
			$str = "";
			if(array_key_exists("tags", $img) && is_array($img["tags"]) && count($img["tags"])) {
				foreach ($img["tags"] as $i => $t) {
					$pos = $img["position_rel"][$i];
					$str .= "$t ".$pos['x_0']." ".$pos['y_0']." ".$pos['x_1']." ".$pos['x_1']."\n";
				}
			} else {
				//dier($img);
			}

			if($str) {
				copy("images/$fn", "$tmp_dir/images/$fn");
				file_put_contents("$tmp_dir/labels/$fn_txt", $str);
			}
		}

		#die("a");
		
		$tmp_zip = "$tmp_dir/yolo_export.zip";
		ob_start();
		system("cd $tmp_dir; zip -r yolo_export.zip .");
		ob_clean();

		header("Content-type: application/zip"); 
		header("Content-Disposition: attachment; filename=data.zip"); 
		header("Pragma: no-cache"); 
		header("Expires: 0"); 

		readfile($tmp_zip);

		ob_start();
		system("rm -rf $tmp_dir");
		ob_clean();
		ini_set('memory_limit', 512000000);
		exit(0);
	} else {
		print "Der Ordner $tmp_name konnte nicht erstellt werden.";
	}
?>
