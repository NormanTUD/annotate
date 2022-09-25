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
		$show_categories = array_filter($_GET["show_categories"]);
	}

	$valid_formats = array(
		"ultralytics_yolov5", "html"
	);
	$format = "ultralytics_yolov5";
	if(isset($_GET["format"]) && in_array($_GET["format"], $valid_formats)) {
		$format = $_GET["format"];
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

								$has_valid_category = 0;
								if(!count($show_categories)) {
									$has_valid_category = 1;
								}

								$images[$file]["w"] = $item["w"];

								$images[$file]["position_rel"][] = parse_position_rel($struct["position"], $images[$file]["w"], $images[$file]["h"]);
								$images[$file]["position_xywh"][] = parse_position_xywh($struct["position"]);
								$images[$file]["position_xyxy"][] = parse_position_xyxy($struct["position"]);
								$images[$file]["anno_struct"] = $struct;
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
											$has_valid_category = 1;
										}
										#dier($images[$file]);
										#die($has_valid_category);
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
		$j = 0;
		foreach ($categories as $i => $cat) {
			if(!count($show_categories) || in_array($cat, $show_categories)) {
				$dataset_yaml .= "  $j: $cat\n";
				$j++;
			}
		}

		ob_start();
		mkdir("$tmp_dir/images/");
		ob_clean();

		if($format == "ultralytics_yolov5") {
			ob_start();
			mkdir("$tmp_dir/labels/");
			ob_clean();

			file_put_contents("$tmp_dir/dataset.yaml", $dataset_yaml);

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
		} else if ($format == "html") {
			ob_start();
			mkdir("$tmp_dir/labels/");
			ob_clean();

			file_put_contents("$tmp_dir/dataset.yaml", $dataset_yaml);

			$html = file_get_contents("export_base.html");
			$html_images = array();

			$annos_strings = array();

			// <object-class> <x> <y> <width> <height>
			foreach ($images as $img) {
				if($img["disabled"]) {
					continue;
				}

				$fn = $img["fn"];
				$w = $img["w"];
				$h = $img["h"];

				$anno_struct = json_decode($img["anno_struct"]["full"], true);

				$id = $anno_struct["id"];

				$annotation_base = '
							<g class="a9s-annotation">
								<rect class="a9s-inner" x="${x_0}" y="${y_0}" width="${x_1}" height="${y_1}"></rect>
							</g>
				';
				$this_annos = array();

				foreach ($img["position_xywh"] as $this_anno_data) {
					$this_anno = $annotation_base;
					$this_anno = preg_replace('/\$\{id\}/', $id, $this_anno);
					$this_anno = preg_replace('/\$\{x_0\}/', $this_anno_data["x"], $this_anno);
					$this_anno = preg_replace('/\$\{x_1\}/', $this_anno_data["w"], $this_anno);
					$this_anno = preg_replace('/\$\{y_0\}/', $this_anno_data["y"], $this_anno);
					$this_anno = preg_replace('/\$\{y_1\}/', $this_anno_data["h"], $this_anno);

					$this_annos[] = $this_anno;
				}


				$annotations_string = join("\n", $this_annos);


				$base_struct = '
				<div style="position: relative; display: inline-block;">
					<img class="images" src="images/'.$fn.'" style="display: block;">
					<svg class="a9s-annotationlayer" viewBox="0 0 '.$w.' '.$height.'">
						<g>
							'.$annotations_string.'
						</g>
					</svg>
				</div>
				';

				$annos_strings[] = $base_struct;
				#dier(htmlentities($base_struct));

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
				}
			}

			$annos_str = join("", $annos_strings);

			$html = preg_replace("/REPLACEME/", $annos_str, $html);

			file_put_contents("$tmp_dir/index.html", $html);
		} else {
			die("This should never happen. Sorry.");
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
