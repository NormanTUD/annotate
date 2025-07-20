<?php
	include_once("functions.php");

	function write_visualization_script($tmp_dir) {
		$train_bash = '#!/bin/bash

FILE=${1:-runs/detect/train/results.csv}

if [ ! -f "$FILE" ]; then
  echo "Error: File '$FILE' not found."
  echo "Provide a CSV path as first argument or place it at runs/detect/train/results.csv"
  exit 1
fi

if ! command -v gnuplot >/dev/null 2>&1; then
  echo "Error: gnuplot is not installed."
  echo "Install with: sudo apt update && sudo apt install gnuplot"
  exit 1
fi

# Extract max epoch number (skip header)
max_epoch=$(tail -n +2 "$FILE" | cut -d',' -f1 | sort -nr | head -n1)

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
  "$FILE" using 1:3 with lines lw line_width lc rgb "red" title "train/box_loss", \\\\
  "$FILE" using 1:4 with lines lw line_width lc rgb "orange" title "train/cls_loss", \\\\
  "$FILE" using 1:5 with lines lw line_width lc rgb "gold" title "train/dfl_loss"

# Plot 2: Validation Losses (smaller = better)
set title "Validation Losses (smaller is better)" font ",11"
set xlabel "Epoch" offset 0,-1
set ylabel "Loss" offset -2,0
set key outside right vertical samplen 1 spacing 1.2 font ",9"
plot \\
  "$FILE" using 1:10 with lines lw line_width lc rgb "blue" title "val/box_loss", \\\\
  "$FILE" using 1:11 with lines lw line_width lc rgb "cyan" title "val/cls_loss", \\\\
  "$FILE" using 1:12 with lines lw line_width lc rgb "green" title "val/dfl_loss"

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

gnuplot -persist "$GNUPLOT_SCRIPT"
rm "$GNUPLOT_SCRIPT"
';

		file_put_contents("$tmp_dir/train", $train_bash);
	}


	function write_train_bash ($tmp_dir, $epochs) {
		$train_bash = '#!/bin/bash

set -e

VENV_PATH="$HOME/.yolov11_venv"

if [[ ! -d $VENV_PATH ]]; then
	echo "$VENV_PATH will be created"
	python3 -m venv $VENV_PATH
	source $VENV_PATH/bin/activate
	echo "Installing YoloV11"
	pip3 install ultralytics
else
	echo "$VENV_PATH already exists"
	source $VENV_PATH/bin/activate
fi

mkdir -p images
IFS=$\'\\n\'
for i in $(ls labels | sed -e "s#\.txt#.jpg#"); do
	if [[ ! -e "images/$i" ]]; then
		wget -nc "'.$GLOBALS["base_url"].'/print_image.php?filename=$i" -O "images/$i"
	fi
done


yolo task=train mode=train data=dataset.yaml epochs='.$epochs.' imgsz='.$GLOBALS["imgsz"].' model=yolo11n.yaml


run_dir=runs/detect/train/weights/
if [[ -d $run_dir ]]
	yolo export model=$run_dir/best.pt format=tfjs

	cp labels.json $run_dir 
else
	echo "$run_dir could not be found"
fi

';

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
			foreach ($images as $fn => $imgname) {
				$w = $imgname[0]["width"];
				$h = $imgname[0]["height"];

				$annotation_base = '
							<g class="a9s-annotation">
								<rect class="a9s-inner" x="${x_0}" y="${y_0}" width="${x_1}" height="${y_1}"></rect>
							</g>
				';

				$this_annos = array();

				$ahref_start = "";
				$ahref_end = "";

				$base_structs[] = $ahref_start.'
					<div class="container_div" style="position: relative; display: inline-block;">
						<img class="images" src="print_image.php?filename='.$fn.'" style="display: block;">
				'.$ahref_end;

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

	function write_bash_files ($tmp_dir, $epochs) {
		write_train_bash($tmp_dir, $epochs);
		write_visualization_script($tmp_name);
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
