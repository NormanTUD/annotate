<?php
	include_once("functions.php");

	function parse_position_rel ($pos, $w, $h) {
		//xywh=pixel:579,354,58,41
		$res = null;
		if(preg_match("/^xywh=pixel:\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)\s*$/", $pos, $matches)) {
			$x_0 = $matches[1];
			$y_0 = $matches[2];

			$x_1 = $matches[3];
			$y_1 = $matches[4];

			$width = abs($x_0 - $x_1);
			$height = abs($y_0 - $y_1);

			$half_width =  $width / 2;
			$half_height = $height / 2;

			$x_center = $x_0 - ($half_width / $w);
			$y_center = $y_0 - ($half_height / $h);

			$rel_width = abs($x_0 - $x_1) / $w;
			$rel_height = abs($y_0 - $y_1) / $h;

			$rel_half_width =  $rel_width / 2;
			$rel_half_height = $rel_height / 2;

			$res["x_center"] = $x_center;
			$res["y_center"] = $y_center;

			$res["x_0"] = $x_0 / $w;
			$res["x_1"] = $x_1 / $w;

			$res["y_0"] = $y_0 / $h;
			$res["y_1"] = $y_1 / $h;

			$res["x"] = $x_0;
			$res["w"] = $x_1;

			$res["y"] = $y_0;
			$res["h"] = $y_1;
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

		$dataset_yaml = "path: ./\ntrain: dataset/images/\nval: dataset/images/\ntest: dataset/images/\nnames:\n";
		$j = 0;
		foreach ($categories as $i => $cat) {
			if(!count($show_categories) || in_array($cat, $show_categories)) {
				$dataset_yaml .= "  $j: $cat\n";
				$j++;
			}
		}

		/*
			git clone --depth 1 https://github.com/ultralytics/yolov5.git
			python3 -m venv env
			source env/bin/activate
			pip3 install -r requirements.txt
			pip3 install "albumentations>=1.0.3"
			wget https://raw.githubusercontent.com/ultralytics/yolov5/b94b59e199047aa8bf2cdd4401ae9f5f42b929e6/data/hyps/hyp.scratch-low.yaml

			pip install wandb

			python3 train.py --cfg yolov5n6.yaml --batch 8 --data dataset.yaml --epochs 10 --cache --img 512 --nosave --hyp hyp.VOC.yaml --hyp hyp.scratch-low.yaml
		 */

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
						if(!count($show_categories)) {
							$str .= "$t ".$pos['x_center']." ".$pos['y_center']." ".$pos['w']." ".$pos['h']."\n";
						} else {
							$k = array_search($img["anno_name"][$i], $show_categories);

							$str .= "$k ".$pos['x_center']." ".$pos['y_center']." ".$pos['w']." ".$pos['h']."\n";
						}
					}
				} else {
					//dier($img);
				}

				if($str) {
					copy("images/$fn", "$tmp_dir/images/$fn");
					file_put_contents("$tmp_dir/labels/$fn_txt", $str);
				}
			}

			$hyperparams = '# YOLOv5 🚀 by Ultralytics, GPL-3.0 license
# Hyperparameters for high-augmentation COCO training from scratch
# python train.py --batch 32 --cfg yolov5m6.yaml --weights "" --data coco.yaml --img 1280 --epochs 300
# See tutorials for hyperparameter evolution https://github.com/ultralytics/yolov5#tutorials

lr0: 0.01  # initial learning rate (SGD=1E-2, Adam=1E-3)
lrf: 0.1  # final OneCycleLR learning rate (lr0 * lrf)
momentum: 0.937  # SGD momentum/Adam beta1
weight_decay: 0.0005  # optimizer weight decay 5e-4
warmup_epochs: 3.0  # warmup epochs (fractions ok)
warmup_momentum: 0.8  # warmup initial momentum
warmup_bias_lr: 0.1  # warmup initial bias lr
box: 0.05  # box loss gain
cls: 0.3  # cls loss gain
cls_pw: 1.0  # cls BCELoss positive_weight
obj: 0.7  # obj loss gain (scale with pixels)
obj_pw: 1.0  # obj BCELoss positive_weight
iou_t: 0.20  # IoU training threshold
anchor_t: 4.0  # anchor-multiple threshold
# anchors: 3  # anchors per output layer (0 to ignore)
fl_gamma: 0.0  # focal loss gamma (efficientDet default gamma=1.5)
hsv_h: 0.015  # image HSV-Hue augmentation (fraction)
hsv_s: 0.7  # image HSV-Saturation augmentation (fraction)
hsv_v: 0.4  # image HSV-Value augmentation (fraction)
degrees: 360  # image rotation (+/- deg)
translate: 0.1  # image translation (+/- fraction)
scale: 0.9  # image scale (+/- gain)
shear: 0.0  # image shear (+/- deg)
perspective: 0.001  # image perspective (+/- fraction), range 0-0.001
flipud: 0.3  # image flip up-down (probability)
fliplr: 0.5  # image flip left-right (probability)
mosaic: 1.0  # image mosaic (probability)
mixup: 0.1  # image mixup (probability)
copy_paste: 0.1  # segment copy-paste (probability)
';

			file_put_contents("$tmp_dir/hyperparams.yaml", $hyperparams);

			$train_bash = '#!/bin/bash
if [ ! -d "yolov5" ]; then
	git clone --depth 1 https://github.com/ultralytics/yolov5.git
fi
cd yolov5
python3 -m venv ~/.yoloenv
if [ -d "$HOME/.yoloenv" ]; then
	echo "~/.yoloenv already exists"
	source ~/env/bin/activate
else
	source ~/yoloenv/bin/activate
	pip3 install -r requirements.txt
	pip3 install "albumentations>=1.0.3"
	pip3 install tensorboard
	pip3 install -r requirements.txt
fi

mkdir -p dataset
if [ -d "../images" ]; then
	mv ../images/ dataset/
fi
if [ -d "../labels" ]; then
	mv ../labels/ dataset/
fi
if [ -e "../dataset.yaml" ]; then
	mv ../dataset.yaml data/
fi
if [ -e "../hyperparams.yaml" ]; then
	mv ../hyperparams.yaml data/hyps/
fi


echo "source ~/.yoloenv/bin/activate"
echo "cd yolov5"
echo "python3 train.py --cfg yolov5n6.yaml --multi-scale --batch 8 --data dataset.yaml --weights \\"\\"  --epochs 500 --cache --img 1024 --nosave --hyp hyperparams.yaml" --evolve

echo "run tensorboard --logdir runs/train to follow visually"
';

			file_put_contents("$tmp_dir/runme.sh", $train_bash);
		} else if ($format == "html") {
			ob_start();
			mkdir("$tmp_dir/labels/");
			ob_clean();

			file_put_contents("$tmp_dir/dataset.yaml", $dataset_yaml);

			$html = file_get_contents("export_base.html");
			$html_images = array();

			$annos_strings = array();

			$annotated_imgs_by_name = array();

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

				foreach ($img["position_rel"] as $this_anno_data) {
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
					<svg class="a9s-annotationlayer" width='.$w.' height='.$h.' viewBox="0 0 '.$w.' '.$h.'">
						<g>
							'.$annotations_string.'
						</g>
					</svg>
				</div>
				';

				$annos_strings[] = $base_struct;
				for ($i = 0; $i < count($img["anno_name"]); $i++) {
					$ttag = $img["anno_name"][$i];
					$annotated_imgs_by_name[$ttag][] = $base_struct;
				}

				#dier(htmlentities($base_struct));

				$fn_txt = preg_replace("/\.(?:jpe?g|png)$/", ".txt", $fn);
				$str = "";
				if(array_key_exists("tags", $img) && is_array($img["tags"]) && count($img["tags"])) {
					foreach ($img["tags"] as $i => $t) {
						$pos = $img["position_rel"][$i];
						$str .= "$t ".$pos['x_0']." ".$pos['x_1']." ".$pos['x_0']." ".$pos['y_1']."\n";
						#dier($str);
					}
				} else {
					//dier($img);
				}

				if($str) {
					#copy("images/$fn", "$tmp_dir/images/$fn");
				}
			}

			$last = "";
			$new_html = "";
			foreach ($annotated_imgs_by_name as $n => $s) {
				if($last != $n) {
					$new_html .= "<h2>".htmlentities($n)."</h2>";
					$last = $n;
				}
				foreach ($s as $i) {
					$new_html .= $i;
				}
			}

			#$annos_str = join("", $annos_strings);

			$html = preg_replace("/REPLACEME/", $new_html, $html);

			#file_put_contents("$tmp_dir/index.html", $html);

			print($html);
			include("footer.php");
			exit;
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
