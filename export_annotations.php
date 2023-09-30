<?php
	ini_set('memory_limit', '16384M');
	ini_set('max_execution_time', '3600');
	set_time_limit(3600);
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

	$max_truncation = 100;

	if(intval(get_get("max_truncation"))) {
		$max_truncation = intval(get_get("max_truncation"));
	}

	$images = [];

	$annotated_image_ids_query = "select SQL_CALC_FOUND_ROWS i.filename, i.width, i.height, c.name, a.x_start, a.y_start, a.w, a.h, a.id, left(i.perception_hash, $max_truncation) as truncated_perception_hash from annotation a left join image i on i.id = a.image_id left join category c on c.id = a.category_id where i.id in (select id from image where id in (select image_id from annotation where deleted = '0' group by image_id)) and i.deleted = 0 ";

	if($show_categories && count($show_categories)) {
		$annotated_image_ids_query .= " and c.name in (".esc($show_categories).") ";
	}

	if($only_uncurated) {
		$annotated_image_ids_query .= " and a.curated is null ";
	}

	if ($format == "html") {
		$annotated_image_ids_query .= " order by i.filename, a.modified ";
		$annotated_image_ids_query .=  " limit ".intval($offset).", ".intval($items_per_page);
	} else if(get_get("limit")) {
		$annotated_image_ids_query .= " order by rand()";
		$annotated_image_ids_query .= " limit ".intval(get_get("limit"));
	} else {
		$annotated_image_ids_query .= " order by rand()";
	}

	$res = rquery($annotated_image_ids_query);

	#dier($annotated_image_ids_query);

	$number_of_rows_query = "SELECT FOUND_ROWS()";
	$number_of_rows_res = rquery($number_of_rows_query);

	$number_of_rows = 0;

	while ($row = mysqli_fetch_row($number_of_rows_res)) {
		$number_of_rows = $row[0];
	}

	$max_page = ceil($number_of_rows / $items_per_page);

	$images = [];
	$categories = [];

	$j = 0;

	$perception_hash_to_image = [];

	// geht einzelne annotationen durch, appendiert die einzelannotationen an die dateinamen
	while ($row = mysqli_fetch_row($res)) {
		if($max_files && $j >= $max_files) {
			continue;
		}

		$filename = $row[0];

		$width = $row[1];
		$height = $row[2];
		$category = $row[3];
		$x_start = $row[4];
		$y_start = $row[5];

		$w = $row[6];
		$h = $row[7];
		$id = $row[8];

		$image_perception_hash = $row[9];

		$category = strtolower($category);
		if(!isset($images[$filename])) {
			$images[$filename] = array();
		}

		$this_annotation = array(
			"width" => $width,
			"height" => $height,
			"category" => $category,
			"x_start" => $x_start,
			"y_start" => $y_start,
			"w" => $w,
			"h" => $h,
			"id" => $id,
			"image_perception_hash" => $image_perception_hash
		);

		if(get_get("group_by_perception_hash")) {
			$perception_hash_to_image[$this_annotation["image_perception_hash"]] = $filename;
		}

		if(!in_array($category, $categories)) {
			$categories[] = $category;
		}

		$yolo = parse_position_yolo(
			$this_annotation["x_start"],
			$this_annotation["y_start"],
			$this_annotation["w"],
			$this_annotation["h"],
			$this_annotation["width"],
			$this_annotation["height"]
		);

		$this_annotation["x_center"] = $yolo["x_center"];
		$this_annotation["y_center"] = $yolo["y_center"];
		$this_annotation["w_rel"] = $yolo["w_rel"];
		$this_annotation["h_rel"] = $yolo["h_rel"];

		$images[$filename][] = $this_annotation;

		$j++;
	}

	if(count($perception_hash_to_image)) {
		$new_images = [];

		foreach ($perception_hash_to_image as $hash => $fn) {
			$new_images[$fn] = $images[$fn];
		}

		$images = $new_images;
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

				if($page_nr == get_get("page")) {
					$page_nr = "<b>$page_nr</b>";
				}

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

				$ahref_start = "";
				$ahref_end = "";

				if(get_get("delete_on_click") && !get_get("no_link")) {
					$ahref_start = "<a target='_blank' href='index.php?edit=$fn'>";
					$ahref_end = "</a>";
				}


				$base_structs[] = $ahref_start.'
					<div '.$delete_str.' style="position: relative; display: inline-block;">
						<img class="images" src="images/'.$fn.'" style="display: block;">
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

		if(get_get("empty")) {
			$empty_images = glob("empty/*.jpg");

			foreach ($empty_images as $fn) {
				$fn = preg_replace("/empty\//", "", $fn);
				if(file_exists("empty/$fn")) {
					$link_to = "$tmp_dir/images/$fn";
					$fn_txt = preg_replace("/\.\w+$/", ".txt", $fn);
					if(get_get("images")) {
						system("ln ".escapeshellarg("empty/$fn")." ".escapeshellarg($link_to));
					}

					file_put_contents("$tmp_dir/labels/$fn_txt", "");
				} else {
					mywarn("\nCannot copy file: empty/$fn\n\n");
				}
			}
		}

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
				#link("images/$fn", $link_to);
				if(get_get("images")) {
					system("ln ".escapeshellarg("images/$fn")." ".escapeshellarg($link_to));
				}
				$j++;

				$str = "";
				$str_arr = array();
				foreach ($img as $single_anno) {
					$str_arr[] = $category_numbers[$single_anno["category"]]." ".$single_anno["x_center"]." ".$single_anno["y_center"]." ".$single_anno["w_rel"]." ".$single_anno["h_rel"];
				}

				$str = join("\n", array_unique($str_arr));

				file_put_contents("$tmp_dir/labels/$fn_txt", "$str\n");
			}
		}

		include("export_helper.php");

		write_yolo_hyperparams($tmp_dir);
		write_train_bash($tmp_dir);
		write_simple_run($tmp_dir);
		write_omniopt_simple_run($tmp_dir);
		write_only_take_first_line($tmp_dir);
		write_remove_labels_with_multiple_entries($tmp_dir);
		write_download_images($tmp_dir);
		write_download_empty($tmp_dir);

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
