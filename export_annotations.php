<?php
	ini_set('memory_limit', '16384M');
	ini_set('max_execution_time', '3600');
	set_time_limit(3600);

	include_once("functions.php");
	include("export_helper.php");

	$show_categories = isset($_GET["show_categories"]) ? $_GET["show_categories"] : [];

	$validation_split = get_get("validation_split", 0);
	$test_split = get_get("test_split", 0);
	$max_files = get_get("max_files", 0);

	$valid_formats = array(
		"ultralytics_yolov5", "html"
	);

	$format = "ultralytics_yolov5";
	if(isset($_GET["format"]) && in_array($_GET["format"], $valid_formats)) {
		$format = $_GET["format"];
	}

	$page = get_param("page");
	$items_per_page = get_param("items_per_page", 500);
	$offset = get_param("offset", $page * $items_per_page);
	$only_uncurated = get_param("only_uncurated");
	$max_truncation = get_param("max_truncation", 100);

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

	if(get_get("curate_on_click") && get_get("delete_on_click")) {
		die("Either curate or delete on click, not both");
	}

	if ($format == "html") {
		print_export_html_and_exit($number_of_rows, $items_per_page, $images);
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

		write_bash_files($tmp_dir);

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
