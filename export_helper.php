<?php
	include_once("functions.php");

	function write_visualization_script($tmp_dir) {
		$train_visualization = '#!/bin/bash

FILE=${1:-runs/detect/train/results.csv}

if [ ! -f "$FILE" ]; then
  echo "Error: File \'$FILE\' not found."
  echo "Provide a CSV path as first argument or place it at runs/detect/train/results.csv"
  exit 1
fi

if ! command -v gnuplot >/dev/null 2>&1; then
  echo "Error: gnuplot is not installed."
  echo "Install with: sudo apt update && sudo apt install gnuplot"
  exit 1
fi

# Extract max epoch number (skip header)
max_epoch=$(tail -n +2 "$FILE" | cut -d\',\' -f1 | sort -nr | head -n1)

# Determine xtic step size dynamically
if (( max_epoch <= 20 )); then
  step=2
elif (( max_epoch <= 50 )); then
  step=5
elif (( max_epoch <= 100 )); then
  step=10
elif (( max_epoch <= 300 )); then
  step=20
else
  step=50
fi

GNUPLOT_SCRIPT=$(mktemp)

cat > "$GNUPLOT_SCRIPT" <<EOF
set datafile separator ","
set multiplot layout 2,2 title "Training Results Overview" font "Arial,10"

set xtics $step
set grid ytics
line_width = 1

# Plot 1: Training Losses (smaller = better)
set title "Training Losses (smaller is better)" font ",11"
set xlabel "Epoch" offset 0,-1
set ylabel "Loss" offset -2,0
set key outside right vertical samplen 1 spacing 1.2 font ",9"
plot \\
  "$FILE" using 1:3 with lines lw line_width lc rgb "red" title "train/box-loss", \\\\
  "$FILE" using 1:4 with lines lw line_width lc rgb "orange" title "train/cls-loss", \\\\
  "$FILE" using 1:5 with lines lw line_width lc rgb "gold" title "train/dfl-loss"

# Plot 2: Validation Losses (smaller = better)
set title "Validation Losses (smaller is better)" font ",11"
set xlabel "Epoch" offset 0,-1
set ylabel "Loss" offset -2,0
set key outside right vertical samplen 1 spacing 1.2 font ",9"
plot \\
  "$FILE" using 1:10 with lines lw line_width lc rgb "blue" title "val/box-loss", \\\\
  "$FILE" using 1:11 with lines lw line_width lc rgb "cyan" title "val/cls-loss", \\\\
  "$FILE" using 1:12 with lines lw line_width lc rgb "green" title "val/dfl-loss"

# Plot 3: Metrics (larger = better)
set title "Metrics (larger is better)" font ",11"
set xlabel "Epoch" offset 0,-1
set ylabel "Value" offset -2,0
set key outside right vertical samplen 1 spacing 1.2 font ",9"
plot \\
  "$FILE" using 1:6 with lines lw line_width lc rgb "magenta" title "precision", \\\\
  "$FILE" using 1:7 with lines lw line_width lc rgb "violet" title "recall", \\\\
  "$FILE" using 1:8 with lines lw line_width lc rgb "purple" title "mAP50", \\\\
  "$FILE" using 1:9 with lines lw line_width lc rgb "dark-violet" title "mAP50-95"

# Plot 4: Learning Rates
set title "Learning Rates (pg0, pg1, pg2)" font ",11"
set xlabel "Epoch" offset 0,-1
set ylabel "LR" offset -2,0
set key outside right vertical samplen 1 spacing 1.2 font ",9"
plot \\
  "$FILE" using 1:13 with lines lw line_width lc rgb "brown" title "lr/pg0", \\\\
  "$FILE" using 1:14 with lines lw line_width lc rgb "dark-orange" title "lr/pg1", \\\\
  "$FILE" using 1:15 with lines lw line_width lc rgb "dark-red" title "lr/pg2"

unset multiplot
EOF

gnuplot -persist "$GNUPLOT_SCRIPT" 2>/dev/null
rm "$GNUPLOT_SCRIPT"
';

		file_put_contents("$tmp_dir/visualize", $train_visualization);
	}


	function write_train_bash ($tmp_dir, $epochs, $model_name) {
		$train_bash = '#!/usr/bin/env bash
set -euo pipefail

DEFAULT_EPOCHS='.$epochs.'
DEFAULT_BATCH=16
DEFAULT_IMGSZ='.$GLOBALS['imgsz'].'
DEFAULT_DEVICE=""
DEFAULT_LR0=0.01
DEFAULT_LRF=0.01
DEFAULT_MOMENTUM=0.937
DEFAULT_WEIGHT_DECAY=0.0005
DEFAULT_WARMUP_EPOCHS=3.0
DEFAULT_WARMUP_MOMENTUM=0.8
DEFAULT_WARMUP_BIAS_LR=0.1
# Augmentations
DEFAULT_HSV_H=0.015
DEFAULT_HSV_S=0.7
DEFAULT_HSV_V=0.4
DEFAULT_DEGREES=0.0
DEFAULT_TRANSLATE=0.1
DEFAULT_SCALE=0.5
DEFAULT_SHEAR=0.0
DEFAULT_PERSPECTIVE=0.0
DEFAULT_FLIPLR=0.5
DEFAULT_MOSAIC=1.0
DEFAULT_MIXUP=0.0
DEFAULT_COPY_PASTE=0.0

VENV_PATH="${HOME}/.yolov11_venv"

if [[ ! -d $VENV_PATH ]]; then
  echo "Creating virtualenv at $VENV_PATH"
  python3 -m venv "$VENV_PATH"
  source "$VENV_PATH/bin/activate"
  pip install ultralytics onnx2tf tf_keras onnx_graphsurgeon sng4onnx
else
  echo "Using existing virtualenv at $VENV_PATH"
  source "$VENV_PATH/bin/activate"
fi

DATA=""
MODEL="'.$model_name.'"
epochs="$DEFAULT_EPOCHS"
batch="$DEFAULT_BATCH"
imgsz="$DEFAULT_IMGSZ"
device="$DEFAULT_DEVICE"
lr0="$DEFAULT_LR0"
lrf="$DEFAULT_LRF"
momentum="$DEFAULT_MOMENTUM"
weight_decay="$DEFAULT_WEIGHT_DECAY"
warmup_epochs="$DEFAULT_WARMUP_EPOCHS"
warmup_momentum="$DEFAULT_WARMUP_MOMENTUM"
warmup_bias_lr="$DEFAULT_WARMUP_BIAS_LR"
hsv_h="$DEFAULT_HSV_H"
hsv_s="$DEFAULT_HSV_S"
hsv_v="$DEFAULT_HSV_V"
degrees="$DEFAULT_DEGREES"
translate="$DEFAULT_TRANSLATE"
scale="$DEFAULT_SCALE"
shear="$DEFAULT_SHEAR"
perspective="$DEFAULT_PERSPECTIVE"
fliplr="$DEFAULT_FLIPLR"
mosaic="$DEFAULT_MOSAIC"
mixup="$DEFAULT_MIXUP"
copy_paste="$DEFAULT_COPY_PASTE"

print_usage() {
  echo "Usage: $0 --data=dataset.yaml --model=yolo11s.yaml [--epochs=N] [--batch=N] [--imgsz=N] ..."
  echo "You can set any YOLO train argument as --arg=value"
}

# Parse CLI args
for arg in "$@"; do
  case $arg in
    --data=*) DATA="${arg#*=}" ;;
    --model=*) MODEL="${arg#*=}" ;;
    --epochs=*) epochs="${arg#*=}" ;;
    --batch=*) batch="${arg#*=}" ;;
    --imgsz=*) imgsz="${arg#*=}" ;;
    --device=*) device="${arg#*=}" ;;
    --lr0=*) lr0="${arg#*=}" ;;
    --lrf=*) lrf="${arg#*=}" ;;
    --momentum=*) momentum="${arg#*=}" ;;
    --weight_decay=*) weight_decay="${arg#*=}" ;;
    --warmup_epochs=*) warmup_epochs="${arg#*=}" ;;
    --warmup_momentum=*) warmup_momentum="${arg#*=}" ;;
    --warmup_bias_lr=*) warmup_bias_lr="${arg#*=}" ;;
    --hsv_h=*) hsv_h="${arg#*=}" ;;
    --hsv_s=*) hsv_s="${arg#*=}" ;;
    --hsv_v=*) hsv_v="${arg#*=}" ;;
    --degrees=*) degrees="${arg#*=}" ;;
    --translate=*) translate="${arg#*=}" ;;
    --scale=*) scale="${arg#*=}" ;;
    --shear=*) shear="${arg#*=}" ;;
    --perspective=*) perspective="${arg#*=}" ;;
    --fliplr=*) fliplr="${arg#*=}" ;;
    --mosaic=*) mosaic="${arg#*=}" ;;
    --mixup=*) mixup="${arg#*=}" ;;
    --copy_paste=*) copy_paste="${arg#*=}" ;;
    --help) print_usage; exit 0 ;;
    *) echo "Unknown argument: $arg"; print_usage; exit 1 ;;
  esac
done

if [[ -z "$DATA" ]]; then
  DATA="dataset.yaml"
fi

if [[ -z "$MODEL" ]]; then
  MODEL="yolo11s.yaml"
fi

mkdir -p images
IFS=$\'\n\'
files=($(ls labels | sed -e "s#\.txt#.jpg#"))
total=${#files[@]}

draw_bar() {
  local progress=$1
  local width=40
  local filled=$((progress * width / 100))
  local empty=$((width - filled))
  printf "["
  printf "%0.s=" $(seq 1 $filled)
  printf "%0.s " $(seq 1 $empty)
  printf "] %3d%%" "$progress"
}

for idx in "${!files[@]}"; do
  file="${files[$idx]}"
  if [[ -e "images/$file" ]]; then
    echo -e "\e[1;33m[$((idx+1))/$total] $file exists, skipping\e[0m"
    continue
  fi

  echo -e "\e[1;34m[$((idx+1))/$total] Downloading $file\e[0m"
  tmp_file=$(mktemp)
  wget -q --show-progress "'.$GLOBALS['base_url'].'//print_image.php?filename=$file" -O "$tmp_file" 2>&1 | while read -r line; do
    if [[ $line =~ ([0-9]{1,3})% ]]; then
      percent="${BASH_REMATCH[1]}"
      printf "\r"
      draw_bar "$percent"
    fi
  done
  mv "$tmp_file" "images/$file"
  echo -e " \e[1;32m✔ Done\e[0m"
done

cmd="yolo detect train data=$DATA model=$MODEL epochs=$epochs batch=$batch imgsz=$imgsz"

cmd+=" lr0=$lr0 lrf=$lrf momentum=$momentum weight_decay=$weight_decay"
cmd+=" warmup_epochs=$warmup_epochs warmup_momentum=$warmup_momentum warmup_bias_lr=$warmup_bias_lr"
cmd+=" hsv_h=$hsv_h hsv_s=$hsv_s hsv_v=$hsv_v"
cmd+=" degrees=$degrees translate=$translate scale=$scale shear=$shear perspective=$perspective"
cmd+=" fliplr=$fliplr mosaic=$mosaic mixup=$mixup copy_paste=$copy_paste"

if [[ -n "$device" ]]; then
  cmd+=" device=$device"
fi

echo "Running: $cmd"
eval "$cmd"
exit_code=$?

if [[ $exit_code -ne 0 ]]; then
  echo "yolo failed with exit-code $exit_code"
  exit $exit_code
fi

run_dir="runs/detect/train/weights/"
if [[ -d $run_dir ]]; then
  echo "Training done, weights in $run_dir"
  exit 0
else
  echo "Error: $run_dir could not be found"
  exit 1
fi';

		file_put_contents("$tmp_dir/train", $train_bash);
	}

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

	function _create_internal_html ($number_of_rows = 0, $items_per_page = 0, $images = [], $html = "") {
		$page_str = "";

		if($items_per_page == 0) {
			return $page_str;
		}

		$max_page = $number_of_rows / $items_per_page;

		if($number_of_rows > $items_per_page) {
			$links = array();
			foreach (range(0, $max_page) as $page_nr) {
				$query = $_GET;
				$query['page'] = $page_nr;
				$query_result = http_build_query($query);

				if($page_nr == get_get("page")) {
					$page_nr = "<b>$page_nr</b>";
				}

				$links[] = "<a href='export_annotations.php?$query_result'>$page_nr</a>";
			}

			$page_str = "<span style='font-size: 1vw'>".join(" &mdash; ", $links)."<br></span>";
			print $page_str;
		}

		if($page_str) {
			print "<br>$page_str<br>";
		}

		if($html == "") {
			$html .= file_get_contents("export_base.html");
		}

		// <object-class> <x> <y> <width> <height>
		if(count($images)) {
			foreach ($images as $fn => $img_data) {
				$w = $img_data[0]["width"];
				$h = $img_data[0]["height"];

				$base_structs[] = '<div class="container_div" style="position: relative; display: inline-block;">';
				$base_structs[] = '  <img class="images" src="print_image.php?filename='.$fn.'" style="display: block;">';

				// ein einziges svg für alle Annotations
				$annotations = [];
				foreach ($img_data as $this_anno_data) {
					$id = isset($this_anno_data["id"]) ? (string)$this_anno_data["id"] : '';
					$cat = isset($this_anno_data["category"]) ? (string)$this_anno_data["category"] : "";
					$x = isset($this_anno_data["x_start"]) ? floatval($this_anno_data["x_start"]) : 0.0;
					$y = isset($this_anno_data["y_start"]) ? floatval($this_anno_data["y_start"]) : 0.0;
					$width  = isset($this_anno_data["w"]) ? floatval($this_anno_data["w"]) : 0.0;
					$height = isset($this_anno_data["h"]) ? floatval($this_anno_data["h"]) : 0.0;

					if ($width < 0) $width = 0;
					if ($height < 0) $height = 0;

					$esc_label = htmlspecialchars($cat, ENT_QUOTES, 'UTF-8');
					$esc_id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');

					$text_x = $x + ($width / 2.0);
					$text_y = max(4, $y - 4);

					$annotations[] = '<g class="a9s-annotation" data-id="'.$esc_id.'" data-label="'.$esc_label.'">'
						. '<rect class="a9s-inner" x="'.$x.'" y="'.$y.'" width="'.$width.'" height="'.$height.'"></rect>'
						. '<text class="a9s-label" x="'.$text_x.'" y="'.$text_y.'" text-anchor="middle" alignment-baseline="baseline" font-size="12" style="pointer-events:auto;">'.$esc_label.'</text>'
						. '</g>';
				}

				$annotations_string = implode("\n", $annotations);

				$base_structs[] = '<svg class="a9s-annotationlayer" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'" style="position:absolute;left:0;top:0;pointer-events:none;" preserveAspectRatio="xMinYMin meet">'
					. '<g>'
					. $annotations_string
					. '</g>'
					. '</svg>';

				$base_structs[] = '</div>';

			}

			$new_html = join("\n", $base_structs);

			$html = preg_replace("/REPLACEME/", $new_html, $html);
		} else {
			$html = "No images for the chosen category";
		}

		return $html;
	}

	function print_export_html_and_exit ($number_of_rows, $items_per_page, $images) {
		$annos_strings = array();

		$html = _create_internal_html($number_of_rows, $items_per_page, $images);

		print($html);


		include("footer.php");
		exit(0);
	}

	function write_bash_files ($tmp_dir, $epochs, $model_name) {
		write_train_bash($tmp_dir, $epochs, $model_name);
		write_visualization_script($tmp_dir);
	}

	function get_number_of_rows () {
		$number_of_rows_query = "SELECT FOUND_ROWS()";
		$number_of_rows_res = rquery($number_of_rows_query);
		$number_of_rows = 0;

		while ($row = mysqli_fetch_row($number_of_rows_res)) {
			$number_of_rows = $row[0];
		}

		return $number_of_rows;
	}

	function get_annotated_image_ids_query ($max_truncation=100, $show_categories=0, $only_uncurated=0, $format="ultralytics_yolov5", $limit=0, $items_per_page=0, $offset=0, $only_curated=0) {
		$annotated_image_ids_query = "select SQL_CALC_FOUND_ROWS i.filename, i.width, i.height, c.name, a.x_start, a.y_start, a.w, a.h, a.id, left(i.perception_hash, $max_truncation) as truncated_perception_hash from annotation a left join image i on i.id = a.image_id left join category c on c.id = a.category_id where i.id in (select id from image where id in (select image_id from annotation where deleted = '0' group by image_id)) and i.deleted = 0 ";

		if($show_categories && count($show_categories)) {
			$annotated_image_ids_query .= " and c.name in (".esc($show_categories).") ";
		}

		if($only_uncurated) {
			$annotated_image_ids_query .= " and (a.curated is null or a.curated = 0 or a.curated = '0') ";
		}

		if($only_curated) {
			$annotated_image_ids_query .= " and (a.curated = 1 or a.curated = '1') "; 
		}

		if ($format == "html") {
			$annotated_image_ids_query .= " order by i.filename, a.modified ";
			$annotated_image_ids_query .=  " limit ".intval($offset).", ".intval($items_per_page);
		} else if($limit) {
			$annotated_image_ids_query .= " order by rand()";
			$annotated_image_ids_query .= " limit ".intval(get_get("limit"));
		} else {
			$annotated_image_ids_query .= " order by rand()";
		}

		return $annotated_image_ids_query;
	}

	function create_tmp_dir () {
		$tmp_name = generateRandomString(20);
		$tmp_dir = "/tmp/$tmp_name";
		while (is_dir($tmp_dir)) {
			$tmp_name = generateRandomString(20);
			$tmp_dir = "tmp/$tmp_name";
		}

		ob_start();
		system("mkdir -p $tmp_dir");
		ob_clean();

		return $tmp_dir;
	}
?>
