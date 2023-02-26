<?php
	ini_set('memory_limit', '4096M');
	ini_set('max_execution_time', '300');
	set_time_limit(300);
	include_once("functions.php");

	$show_categories = isset($_GET["show_categories"]) ? $_GET["show_categories"] : [];

	$validation_split = get_get("validation_split", 0);
	$test_split = get_get("test_split", 0);
	$max_files = get_get("max_files", 0);

	function get_rand_between_0_and_1 () {
		return mt_rand() / mt_getrandmax();
	}

	function parse_position_yolo ($x, $y, $w, $h, $imgw, $imgh) {
		if(0 > $x) { $x = 0; }
		if(0 > $y) { $y = 0; }
		if(0 > $w) { $w = 0; }
		if(0 > $h) { $h = 0; }

		$res["x_center"] = (((2 * $x) + $w) / 2) / $imgw;
		$res["y_center"] = (((2 * $y) + $h) / 2) / $imgh;

		$res["w_rel"] = $w / $imgw;
		$res["h_rel"] = $h / $imgh;

		return $res;
	}

	$valid_formats = array(
		"ultralytics_yolov5", "html"
	);
	$format = "ultralytics_yolov5";
	if(isset($_GET["format"]) && in_array($_GET["format"], $valid_formats)) {
		$format = $_GET["format"];
	}

	$page = 0;
	if(get_get("page")) {
		$page = intval(get_get("page"));
	}

	$items_per_page = 500;
	if(get_get("items_per_page")) {
		$items_per_page = intval(get_get("items_per_page"));
	}

	$offset = $page * $items_per_page;
	if(get_get("offset")) {
		$offset = intval(get_get("offset"));
	}

	$only_uncurated = 0;
	if(get_get("only_uncurated")) {
		$only_uncurated = intval(get_get("only_uncurated"));
	}

	$images = [];
	
	$annotated_image_ids_query = "select SQL_CALC_FOUND_ROWS i.filename, i.width, i.height, c.name, a.x_start, a.y_start, a.w, a.h, a.id from annotation a left join image i on i.id = a.image_id left join category c on c.id = a.category_id where i.id in (select id from image where id in (select image_id from annotation where deleted = 0 group by image_id)) and i.deleted = 0";

	if(count($show_categories)) {
		$annotated_image_ids_query .= " and c.name in (".esc($show_categories).")";
	}

	if($only_uncurated) {
		$annotated_image_ids_query .= " and a.curated is null";
	}

	$annotated_image_ids_query .= " order by i.filename, a.modified";

	if ($format == "html") {
		$annotated_image_ids_query .=  " limit ".intval($offset).", ".intval($items_per_page);
	}

	#dier($annotated_image_ids_query);

	$res = rquery($annotated_image_ids_query);

	$number_of_rows_query = "SELECT FOUND_ROWS()";
	$number_of_rows_res = rquery($number_of_rows_query);

	$number_of_rows = 0;

	while ($row = mysqli_fetch_row($number_of_rows_res)) {
		$number_of_rows = $row[0];
	}

	$max_page = ceil($number_of_rows / $items_per_page);

	$images = [];
	$categories = [];

	while ($row = mysqli_fetch_row($res)) {
		$row[3] = strtolower($row[3]);
		if(!isset($images[$row[0]])) {
			$images[$row[0]] = array();
		}

		$this_annotation = array(
			"width" => $row[1],
			"height" => $row[2],
			"category" => $row[3],
			"x_start" => $row[4],
			"y_start" => $row[5],
			"w" => $row[6],
			"h" => $row[7],
			"id" => $row[8],
		);

		if(!in_array($row[3], $categories)) {
			$categories[] = $row[3];
		}

		$yolo = parse_position_yolo($this_annotation["x_start"], $this_annotation["y_start"], $this_annotation["w"], $this_annotation["h"], $this_annotation["width"], $this_annotation["height"]);

		$this_annotation["x_center"] = $yolo["x_center"];
		$this_annotation["y_center"] = $yolo["y_center"];
		$this_annotation["w_rel"] = $yolo["w_rel"];
		$this_annotation["h_rel"] = $yolo["h_rel"];

		$images[$row[0]][] = $this_annotation;
	}

	if ($format == "html") {
		$html = file_get_contents("export_base.html");
		$annos_strings = array();

		$page_str = "";

		if($number_of_rows > $items_per_page) {
			$links = array();
			foreach (range(0, $max_page - 1) as $page_nr) {
				$query = $_GET;
				$query['page'] = $page_nr;
				$query_result = http_build_query($query);

				$links[] = "<a href='export_annotations.php?$query_result'>$page_nr</a>";
			}

			$page_str = "<span style='font-size: 1vw'>".join(" &mdash; ", $links)."<br></span>";
			print $page_str;
		}

		// <object-class> <x> <y> <width> <height>
		if(count($images)) {
			foreach ($images as $fn => $imgname) {
				$w = $imgname[0]["width"];
				$h = $imgname[0]["height"];

				$annotation_base = '
							<g class="a9s-annotation">
								<rect class="a9s-inner" x="${x_0}" y="${y_0}" width="${x_1}" height="${y_1}"></rect>
							</g>
				';

				$this_annos = array();

				$delete_str = "";

				if(get_get("curate_on_click") && get_get("delete_on_click")) {
					die("Either curate or delete on click, not both");
				}

				if(get_get("curate_on_click")) {
					$delete_str = 'onclick="curate_anno(\'' . $fn . '\')"';
				}

				if(get_get("delete_on_click")) {
					$delete_str = 'onclick="delete_all_anno(\'' . $fn . '\')"';
				}

				$base_structs[] = '
					<div '.$delete_str.' style="position: relative; display: inline-block;">
						<img class="images" src="images/'.$fn.'" style="display: block;">
				';

				foreach ($imgname as $this_anno_data) {
					$this_anno = $annotation_base;

					$this_anno = preg_replace('/\$\{id\}/', $this_anno_data["id"], $this_anno);
					$this_anno = preg_replace('/\$\{x_0\}/', $this_anno_data["x_start"], $this_anno);
					$this_anno = preg_replace('/\$\{x_1\}/', $this_anno_data["w"], $this_anno);
					$this_anno = preg_replace('/\$\{y_0\}/', $this_anno_data["y_start"], $this_anno);
					$this_anno = preg_replace('/\$\{y_1\}/', $this_anno_data["h"], $this_anno);

					$this_annos[] = $this_anno;

					$annotations_string = join("\n", $this_annos);


					$base_struct = '
						<svg class="a9s-annotationlayer" width='.$w.' height='.$h.' viewBox="0 0 '.$w.' '.$h.'">
							<g>
								'.$annotations_string.'
							</g>
						</svg>
					';

					#dier($annotations_string);

					$base_structs[] = $base_struct;
				}

				$base_structs[] = "</div>";
			}

			$new_html = join("\n", $base_structs);

			$html = preg_replace("/REPLACEME/", $new_html, $html);

			print($html);
		} else {
			print "Keine Daten für die gewählte Kategorie";
		}

		if($page_str) {
			print "<br>$page_str<br>";
		}

		include("footer.php");
		exit(0);
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

	if(is_dir($tmp_dir)) {
		$dataset_yaml = "path: ./\n";
		$dataset_yaml .= "train: dataset/images/\n";
		if($validation_split) {
			$dataset_yaml .= "val: dataset/validation/\n";
		} else {
			$dataset_yaml .= "val: dataset/images/\n";
		}

		$dataset_yaml .= "names:\n";

		$j = 0;
		$category_numbers = array();
		foreach ($categories as $i => $cat) {
			$category_numbers[$cat] = $j;
			$dataset_yaml .= "  $j: $cat\n";
			$j++;
		}

		ob_start();
		mkdir("$tmp_dir/images/");
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

		foreach ($images as $fn => $img) {
			$fn_txt = preg_replace("/\.\w+$/", ".txt", $fn);
			$link_to = "$tmp_dir/images/$fn";

			/*
			$failed_link = link("images/$fn", $link_to);
			#Make the parent directory of the link() target have permission chmod u=rwx,g=rxs,o=rx. This should show up in "ls" as "drwxr-sr-x". In this case, the user.group ownership is wwwrun.www.
			if(!$failed_link) {
				dier("failed to copy >images/$fn< to >$link_to<");
			}
			 */
			if(file_exists("images/$fn")) {
				link("images/$fn", $link_to);
				$j++;

				$str = "";
				$str_arr = array();
				foreach ($img as $single_anno) {
					$str_arr[] = $category_numbers[$single_anno["category"]]." ".$single_anno["x_center"]." ".$single_anno["y_center"]." ".$single_anno["w_rel"]." ".$single_anno["h_rel"];
				}

				$str = join("\n", array_unique($str_arr));

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

#SBATCH -n 3 --time=64:00:00 --mem-per-cpu=60000 --partition=alpha --gres=gpu:1

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

		#die("a");
		
		$tmp_zip = "$tmp_dir/yolo_export.zip";
		ob_start();
		system("cd $tmp_dir; zip --symlinks -r yolo_export.zip .");
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
