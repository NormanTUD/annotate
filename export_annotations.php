<?php
	ini_set('memory_limit', '4096M');
	ini_set('max_execution_time', '300');
	set_time_limit(300);
	include_once("functions.php");

	$validation_split = get_get("validation_split", 0);
	$test_split = get_get("test_split", 0);
	$max_files = get_get("max_files", 0);

	function get_rand_between_0_and_1 () {
		return mt_rand() / mt_getrandmax();
	}


	function parse_position_rel ($pos, $imgw, $imgh) {
		//xywh=pixel:579,354,58,41
		$res = null;
		if(preg_match("/^xywh=pixel:\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)\s*$/", $pos, $matches)) {
			$x = $matches[1];
			$y = $matches[2];

			$w = $matches[3];
			$h = $matches[4];

		}
	}

	function parse_position_yolo ($file, $img_file, $pos, $imgw, $imgh) {
		//xywh=pixel:579,354,58,41
		$res = array();

		if(preg_match("/^xywh=pixel:\s*(-?\d+),\s*(-?\d+),\s*(-?\d+),\s*(-?\d+)\s*$/", $pos, $matches)) {
			try {
				$exif = @exif_read_data("images/$file");
			} catch (\Throwable $e) {
				//;
			}

			if(isset($exif["Orientation"]) && $exif['Orientation'] == 6) {
				list($imgw, $imgh) = array($imgh, $imgw);
			}

			$x = $matches[1];
			$y = $matches[2];

			$w = $matches[3];
			$h = $matches[4];

			if(0 > $x) { $x = 0; }
			if(0 > $y) { $y = 0; }
			if(0 > $w) { $w = 0; }
			if(0 > $h) { $h = 0; }

			$res["x_center"] = (((2 * $x) + $w) / 2) / $imgw;
			$res["y_center"] = (((2 * $y) + $h) / 2) / $imgh;

			$res["w_rel"] = $w / $imgw;
			$res["h_rel"] = $h / $imgh;
		} else {
			mywarn("Position is undefined for $file\n");
			#die($pos);
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
		$files = scandir("images");
		$images = array();

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file)) {
				$imgfn = "images/$file";
				$imgsz = getimagesize($imgfn);

				if($imgsz) {
					$width = $imgsz[0];
					$height = $imgsz[1];

					#dier($imgsz);
					#dier($file);
					$hash = hash("sha256", $file);
					$dir = "annotations/$hash";

					if(is_dir($dir) && $width && $height && file_exists($imgfn)) {
						$images[$file] = array(
							"fn" => $file, 
							"hash" => $hash,
							"dir" => $dir,
							"w" => $width,
							"h" => $height
						);
					}
				} else {
					error_log("Error reading file $file");
				}
			}
		}

		$categories = array();
		$annos = array();

		$filtered_imgs = array();

		$k = 0;
		foreach($images as $item) {
			if(is_dir($item["dir"])) {
				$user_annotations = scandir($item["dir"]);
				foreach($user_annotations as $user_annotation_dir) {
					if(!preg_match("/^\.\.?$/", $user_annotation_dir)) {
						$tdir = $item["dir"]."/$user_annotation_dir";
						$single_user_annotations = scandir($tdir);
						foreach($single_user_annotations as $single_user_annotation_file) {
							if(preg_match("/\.json$/", $single_user_annotation_file)) {
								#die("<pre>$single_user_annotation_file</pre>");
								$struct = get_json_cached("$tdir/$single_user_annotation_file");
								#dier($struct);
								$file = $struct["source"];
								//mywarn($file."\n");
								#dier($images[$file]);

								$has_valid_category = 0;

								if(!count($show_categories)) {
									$has_valid_category = 1;
								}

								$images[$file]["w"] = $item["w"];
								$images[$file]["h"] = $item["h"];

								$images[$file]["position_rel"][] = parse_position_rel($struct["position"], $images[$file]["w"], $images[$file]["h"]);

								#if($file == "lentikularwolke-OiFzWcEWGN4-00028.jpg") {
								#	die(print_r($struct, true));
								#}

								$images[$file]["position_yolo"][] = parse_position_yolo($file, $images[$file], $struct["position"], $images[$file]["w"], $images[$file]["h"]);
								$images[$file]["position_xywh"][] = parse_position_xywh($struct["position"]);
								$images[$file]["anno_struct"] = $struct;
								$bla = print_r($struct["body"], true);

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
										if(in_array($anno["value"], $show_categories)) {
											$has_valid_category = 1;
										}
										#dier($images[$file]);
										#die(">$has_valid_category<");
										//dier($index);
										//dier($anno["value"]);
									}
								}


								if(!$has_valid_category) {
									#print "no valid category $file<br><span style='color: red'>disabling entry for $file</span><br>\n";
									unset($images[$file]["disabled"]);
								} else {
									if(file_exists("images/$file")) {
										if(isset($images[$file])) {
											if(!array_key_exists("fn", $images[$file])) {
												$images[$file]["fn"] = $file;
											}

											$filtered_imgs[$file] = $images[$file];
										}
									}
								}

								#if(preg_match("/jupiter/", $bla)) {
								#	dier("has_valid_category: $has_valid_category\nss:\n$bla");
								#}
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

		$dataset_yaml = "path: ./\n";
		$dataset_yaml .= "train: dataset/images/\n";
		if($validation_split) {
			$dataset_yaml .= "val: dataset/validation/\n";
		} else {
			$dataset_yaml .= "val: dataset/images/\n";
		}

		if($test_split) {
			$dataset_yaml .= "test: dataset/test/\n";
		}
		$dataset_yaml .= "names:\n";

		$j = 0;
		$category_numbers = array();
		foreach ($categories as $i => $cat) {
			if(!count($show_categories) || in_array($cat, $show_categories)) {
				$category_numbers[$cat] = $j;
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

			if($validation_split) {
				mkdir("$tmp_dir/validation/");
			}

			if($test_split) {
				mkdir("$tmp_dir/test/");
			}

			file_put_contents("$tmp_dir/dataset.yaml", $dataset_yaml);

			$j = 0;

			shuffle($filtered_imgs);

			foreach ($filtered_imgs as $img) {
				if(!array_key_exists("fn", $img)) {
					error_log("fn not defined:");
					error_log(print_r($img, true));
					continue;
				}
				if($max_files == 0 || $j < $max_files) {
					$fn = $img["fn"];
					//dier($img);
					#print "<br>$fn<br>";
					$fn_txt = preg_replace("/\.(?:jpe?g|png)$/", ".txt", $fn);
					$str = "";
					$strarr = array();
					if(array_key_exists("tags", $img) && is_array($img["tags"]) && count($img["tags"])) {
						foreach ($img["tags"] as $i => $t) {
							$pos = $img["position_yolo"][$i];
							$k = $category_numbers[$img["anno_name"][$i]];
							if(isset($pos['x_center']) && isset($pos['y_center']) &&  isset($pos['w_rel']) && isset($pos['h_rel'])) {
								$this_str = "$k ".$pos['x_center']." ".$pos['y_center']." ".$pos['w_rel']." ".$pos['h_rel'];
								if (!in_array($this_str, $strarr)) {
									$strarr[] = $this_str;
								}
							} else {
								error_log("$fn misses x_center, y_center, w_rel or h_rel");
								#die(print_r($img, true));
							}
						}

						$str = implode("\n", $strarr);
					} else {
						//dier($img);
					}

					if($str && $fn && $fn_txt) {
						$copy_to = "$tmp_dir/images/$fn";
						if($validation_split || $test_split) {
							$max_val = count($filtered_imgs) * $validation_split;
							$max_test = count($filtered_imgs) * $test_split;

							if($validation_split && $test_split) {
								if(get_rand_between_0_and_1() >= 0.5) {
									if($j <= $max_val) {
										$copy_to = "$tmp_dir/validation/$fn";
									}
								} else {
									if ($j <= $max_test) {
										$copy_to = "$tmp_dir/test/$fn";
									}
								}
							} else {
								if($validation_split) {
									if($j <= $max_val) {
										$copy_to = "$tmp_dir/validation/$fn";
									}
								}
								if($test_split) {
									if($j <= $max_test) {
										$copy_to = "$tmp_dir/test/$fn";
									}
								}
							}
						}

						copy("images/$fn", $copy_to);
						$j++;
						file_put_contents("$tmp_dir/labels/$fn_txt", $str);
					}
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
mixup: 0.3  # image mixup (probability)
copy_paste: 0.4  # segment copy-paste (probability)
';

			file_put_contents("$tmp_dir/hyperparams.yaml", $hyperparams);

			$train_bash = '#!/bin/bash
if [ ! -d "yolov5" ]; then
	git clone --depth 1 https://github.com/ultralytics/yolov5.git
fi
cd yolov5
ml modenv/hiera GCCcore/11.3.0 Python/3.9.6
if [ -d "$HOME/.alpha_yoloenv" ]; then
	python3 -m venv ~/.alpha_yoloenv
	echo "~/.alpha_yoloenv already exists"
	source ~/.alpha_yoloenv/bin/activate
else
	python3 -mvenv ~/.alpha_yoloenv/
	source ~/.alpha_yoloenv/bin/activate
	pip3 install -r requirements.txt
	pip3 install "albumentations>=1.0.3"
	pip3 install -r requirements.txt
fi

mkdir -p dataset
if [ -d "../images" ]; then
	mv ../images/ dataset/
fi
if [ -d "../validation" ]; then
	mv ../validation/ dataset/
fi
if [ -d "../test" ]; then
	mv ../test/ dataset/
fi
if [ -d "../labels" ]; then
	mv ../labels/ dataset/
fi
if [ -e "../dataset.yaml" ]; then
	mv ../dataset.yaml data/
fi
if [ -e "../omniopt_simple_run.sh" ]; then
	mv ../omniopt_simple_run.sh .
fi
if [ -e "../simple_run.sh" ]; then
	mv ../simple_run.sh .
fi
if [ -e "../run.sh" ]; then
	mv ../run.sh .
fi
if [ -e "../hyperparams.yaml" ]; then
	mv ../hyperparams.yaml data/hyps/
fi


echo "ml modenv/hiera GCCcore/11.3.0 Python/3.9.6"
echo "source ~/.alpha_yoloenv/bin/activate"
echo "cd yolov5"
echo "sbatch -n 1 --time=64:00:00 --mem-per-cpu=32000 --partition=alpha --gres=gpu:1 run.sh"
';

			file_put_contents("$tmp_dir/runme.sh", $train_bash);

			$simple_run_bash = '#!/bin/bash

#SBATCH -n 2 --time=32:00:00 --mem-per-cpu=32000 --partition=alpha --gres=gpu:1

python3 train.py --cfg yolov5s.yaml --multi-scale --batch 130 --data data/dataset.yaml --epochs 1500 --cache --img 512 --hyp data/hyps/hyperparams.yaml --patience 200
';

			file_put_contents("$tmp_dir/simple_run.sh", $simple_run_bash);

			$omniopt_simple_run = "#!/bin/bash -l

SCRIPT_DIR=$( cd -- \"\$( dirname -- \"\${BASH_SOURCE[0]}\" )\" &> /dev/null && pwd )

cd \$SCRIPT_DIR

ml modenv/hiera GCCcore/11.3.0 Python/3.9.6

if [[ ! -e ~/.alpha_yoloenv/bin/activate ]]; then
	python3 -mvenv ~/.alpha_yoloenv/
	source ~/.alpha_yoloenv/bin/activate
	pip3 install -r requirements.txt
fi

source ~/.alpha_yoloenv/bin/activate



function echoerr() {
	echo \"$@\" 1>&2
}

function red_text {
        echoerr -e \"\e[31m$1\e[0m\"
}

set -e
set -o pipefail
set -u

function calltracer () {
        echo 'Last file/last line:'
        caller
}
trap 'calltracer' ERR

function help () {
        echo \"Possible options:\"
        echo \"  --batchsize=INT                                    default value: 130\"
        echo \"  --epochs=INT                                       default value: 1500\"
        echo \"  --img=INT                                          default value: 512\"
        echo \"  --patience=INT                                     default value: 200\"
	echo \"	--lr0=FLOAT                                        default value: 0.01\"
	echo \"	--lrf=FLOAT                                        default value: 0.1\"
	echo \"	--momentum=FLOAT                                   default value: 0.937\"
	echo \"	--weight_decay=FLOAT                               default value: 0.0005\"
	echo \"	--warmup_epochs=FLOAT                              default value: 3.0\"
	echo \"	--warmup_momentum=FLOAT                            default value: 0.8\"
	echo \"	--warmup_bias_lr=FLOAT                             default value: 0.1\"
	echo \"	--box=FLOAT                                        default value: 0.05\"
	echo \"	--cls=FLOAT                                        default value: 0.3\"
	echo \"	--cls_pw=FLOAT                                     default value: 1.0\"
	echo \"	--obj=FLOAT                                        default value: 0.7\"
	echo \"	--obj_pw=FLOAT                                     default value: 1.0\"
	echo \"	--iou_t=FLOAT                                      default value: 0.20\"
	echo \"	--anchor_t=FLOAT                                   default value: 4.0\"
	echo \"	--fl_gamma=FLOAT                                   default value: 0.0\"
	echo \"	--hsv_h=FLOAT                                      default value: 0.015\"
	echo \"	--hsv_s=FLOAT                                      default value: 0.7\"
	echo \"	--hsv_v=FLOAT                                      default value: 0.4\"
	echo \"	--degrees=FLOAT                                    default value: 360\"
	echo \"	--translate=FLOAT                                  default value: 0.1\"
	echo \"	--scale=FLOAT                                      default value: 0.9\"
	echo \"	--shear=FLOAT                                      default value: 0.0\"
	echo \"	--perspective=FLOAT                                default value: 0.001\"
	echo \"	--flipud=FLOAT                                     default value: 0.3\"
	echo \"	--fliplr=FLOAT                                     default value: 0.5\"
	echo \"	--mosaic=FLOAT                                     default value: 1.0\"
	echo \"	--mixup=FLOAT                                      default value: 0.3\"
	echo \"	--copy_paste=FLOAT                                 default value: 0.4\"
        echo \"  --model\"
        echo \"  --help                                             this help\"
        echo \"  --debug                                            Enables debug mode (set -x)\"
        exit $1
}

export batchsize=130
export epochs=1500
export img=512
export patience=200
export model=yolov5s.yaml
export img=512
export patience=200
export lr0=0.01
export lrf=0.1
export momentum=0.937
export weight_decay=0.0005
export warmup_epochs=3.0
export warmup_momentum=0.8
export warmup_bias_lr=0.1
export box=0.05
export cls=0.3
export cls_pw=1.0
export obj=0.7
export obj_pw=1.0
export iou_t=0.20
export anchor_t=4.0
export fl_gamma=0.0
export hsv_h=0.015
export hsv_s=0.7
export hsv_v=0.4
export degrees=360
export translate=0.1
export scale=0.9
export shear=0.0
export perspective=0.001
export flipud=0.3
export fliplr=0.5
export mosaic=1.0
export mixup=0.3
export copy_paste=0.4

for i in $@; do
        case \$i in
                --batchsize=*)
                        batchsize=\"\${i#*=}\"
                        re='^[+-]?[0-9]+$'
                        if ! [[ \$batchsize =~ \$re ]] ; then
                                red_text \"error: Not a INT: \$i\" >&2
                                help 1
                        fi
                        shift
                        ;;
                --epochs=*)
                        epochs=\"\${i#*=}\"
                        re='^[+-]?[0-9]+$'
                        if ! [[ \$epochs =~ \$re ]] ; then
                                red_text \"error: Not a INT: \$i\" >&2
                                help 1
                        fi
                        shift
                        ;;
                --img=*)
                        img=\"\${i#*=}\"
                        re='^[+-]?[0-9]+$'
                        if ! [[ \$img =~ \$re ]] ; then
                                red_text \"error: Not a INT: \$i\" >&2
                                help 1
                        fi
                        shift
                        ;;
                --patience=*)
                        patience=\"\${i#*=}\"
                        re='^[+-]?[0-9]+$'
                        if ! [[ \$patience =~ \$re ]] ; then
                                red_text \"error: Not a INT: \$i\" >&2
                                help 1
                        fi
                        shift
                        ;;
                --model=*)
                        model=\"\${i#*=}\"
                        shift
                        ;;
		--lr0=*)
			lr0=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$lr0 =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--lrf=*)
			lrf=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$lrf =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--momentum=*)
			momentum=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$momentum =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--weight_decay=*)
			weight_decay=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$weight_decay =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--warmup_epochs=*)
			warmup_epochs=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$warmup_epochs =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--warmup_momentum=*)
			warmup_momentum=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$warmup_momentum =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--warmup_bias_lr=*)
			warmup_bias_lr=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$warmup_bias_lr =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--box=*)
			box=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$box =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--cls=*)
			cls=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$cls =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--cls_pw=*)
			cls_pw=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$cls_pw =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--obj=*)
			obj=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$obj =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--obj_pw=*)
			obj_pw=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$obj_pw =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--iou_t=*)
			iou_t=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$iou_t =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--anchor_t=*)
			anchor_t=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$anchor_t =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--fl_gamma=*)
			fl_gamma=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$fl_gamma =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--hsv_h=*)
			hsv_h=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$hsv_h =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--hsv_s=*)
			hsv_s=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$hsv_s =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--hsv_v=*)
			hsv_v=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$hsv_v =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--degrees=*)
			degrees=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$degrees =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--translate=*)
			translate=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$translate =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--scale=*)
			scale=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$scale =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--shear=*)
			shear=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$shear =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--perspective=*)
			perspective=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$perspective =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--flipud=*)
			flipud=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$flipud =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--fliplr=*)
			fliplr=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$fliplr =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--mosaic=*)
			mosaic=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$mosaic =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--mixup=*)
			mixup=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$mixup =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
		--copy_paste=*)
			copy_paste=\"\${i#*=}\"
			re=\"^[+-]?[0-9]+([.][0-9]+)?$\"
			if ! [[ \$copy_paste =~ \$re ]] ; then
				red_text \"error: Not a FLOAT: \$i\" >&2
				help 1
			fi
			shift
			;;
                -h|--help)
                        help 0
                        ;;
                --debug)
                        set -x
                        ;;
                *)
                        red_text \"Unknown parameter \$i\" >&2
                        help 1
                        ;;
        esac
done

run_uuid=\$(uuidgen)

hyps_file=\$SCRIPT_DIR/data/hyps/hyperparam_\${run_uuid}.yaml

hyperparams_file_contents=\"
# YOLOv5 🚀 by Ultralytics, GPL-3.0 license
# Hyperparameters for high-augmentation COCO training from scratch
# python train.py --batch 32 --cfg yolov5m6.yaml --weights \"\" --data coco.yaml --img 1280 --epochs 300
# See tutorials for hyperparameter evolution https://github.com/ultralytics/yolov5#tutorials

lr0: \$lr0 # initial learning rate (SGD=1E-2, Adam=1E-3)
lrf: \$lrf # final OneCycleLR learning rate (lr0 * lrf)
momentum: \$momentum # SGD momentum/Adam beta1
weight_decay: \$weight_decay # optimizer weight decay 5e-4
warmup_epochs: \$warmup_epochs # warmup epochs (fractions ok)
warmup_momentum: \$warmup_momentum # warmup initial momentum
warmup_bias_lr: \$warmup_bias_lr # warmup initial bias lr
box: \$box # box loss gain
cls: \$cls # cls loss gain
cls_pw: \$cls_pw # cls BCELoss positive_weight
obj: \$obj # obj loss gain (scale with pixels)
obj_pw: \$obj_pw # obj BCELoss positive_weight
iou_t: \$iou_t # IoU training threshold
anchor_t: \$anchor_t # anchor-multiple threshold
# anchors: $# anchors # anchors per output layer (0 to ignore)
fl_gamma: \$fl_gamma # focal loss gamma (efficientDet default gamma=1.5)
hsv_h: \$hsv_h # image HSV-Hue augmentation (fraction)
hsv_s: \$hsv_s # image HSV-Saturation augmentation (fraction)
hsv_v: \$hsv_v # image HSV-Value augmentation (fraction)
degrees: \$degrees # image rotation (+/- deg)
translate: \$translate # image translation (+/- fraction)
scale: \$scale # image scale (+/- gain)
shear: \$shear # image shear (+/- deg)
perspective: \$perspective # image perspective (+/- fraction), range 0-0.001
flipud: \$flipud # image flip up-down (probability)
fliplr: \$fliplr # image flip left-right (probability)
mosaic: \$mosaic # image mosaic (probability)
mixup: \$mixup # image mixup (probability)
copy_paste: \$copy_paste # segment copy-paste (probability)
\"

echo \"\$hyperparams_file_contents\" > \"\$hyps_file\"

python3 \$SCRIPT_DIR/train.py --cfg \"\$model\" --multi-scale --batch \$batchsize --data \$SCRIPT_DIR/data/dataset.yaml --epochs \$epochs --cache --img \$img --hyp \"\$hyps_file\" --patience \$patience 2>&1 \
        | awk '{print;print > \"/dev/stderr\"}' \
        | egrep '[0-9]G' \
        | egrep '[0-9]/[0-9]' \
        | grep -v Class \
        | sed -e 's/.*G\s*//' \
        | egrep '^[0-9]+\.[0-9]+' \
        | tail -n1 \
        | sed -e 's/\s*[0-9]*\s*[0-9]*:.*//' \
        | sed -e 's#\s\s*#\\n#g' \
        | perl -e '\$i = 1; while (<>) { print qq#RESULT\$i: \$_#; \$i++; }'

";

			file_put_contents("$tmp_dir/omniopt_simple_run.sh", $omniopt_simple_run);
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
			foreach ($filtered_imgs as $img) {
				if(!isset($img["anno_struct"]["full"])) {
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
					<svg class="a9s-annotationlayer" width='.$w.' height='.$h.' viewBox="0 0 '.$w.' '.$h.'">
						<g>
							'.$annotations_string.'
						</g>
					</svg>
				</div>
				';

				$annos_strings[] = $base_struct;
				if(is_array($img["anno_name"])) {
					for ($i = 0; $i < count($img["anno_name"]); $i++) {
						$ttag = $img["anno_name"][$i];
						$annotated_imgs_by_name[$ttag][] = $base_struct;
					}
				}

				#dier(htmlentities($base_struct));

				$fn_txt = preg_replace("/\.(?:jpe?g|png)$/", ".txt", $fn);
				$str = "";
				if(array_key_exists("tags", $img) && is_array($img["tags"]) && count($img["tags"])) {
					foreach ($img["tags"] as $i => $t) {
						$pos = $img["position_rel"][$i];
						$str .= "$t ".$pos['x_0']." ".$pos['x_1']." ".$pos['x_0']." ".$pos['y_1']."\n";
					}
				}
			}

			$last = "";
			$new_html = "";
			$hashes = array();
			foreach ($annotated_imgs_by_name as $n => $s) {
				if($last != $n) {
					if(in_array($n, $show_categories)) {
						$new_html .= "<h2>".htmlentities($n)."</h2>";
						$last = $n;
					}
				}
				foreach ($s as $i) {
					$h = hash('md5', htmlentities($i));
					if(!in_array($h, $hashes)) {
						$new_html .= $i;
						$hashes[] = $h;
					}
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
		header("Content-Length: ".filesize($tmp_zip));

		#readfile($tmp_zip);
		$handle = @fopen($tmp_zip, "r");
		if ($handle) {
			while (($buffer = fgets($handle, 4096)) !== false) {
				echo $buffer;
			}
			fclose($handle);
		}

		ob_start();
		system("rm -rf $tmp_dir");
		ob_clean();
		exit(0);
	} else {
		print "Der Ordner $tmp_name konnte nicht erstellt werden.";
	}
?>
