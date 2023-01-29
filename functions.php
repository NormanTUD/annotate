<?php
	$GLOBALS["get_current_tags_cache"] = array();
	$GLOBALS["queries"] = array();
	$GLOBALS["db_name"] = "annotate";

	if(!isset($GLOBALS['db_host'])) {
		$GLOBALS['db_host'] = 'localhost';
	}

	if(!isset($GLOBALS['db_username'])) {
		$GLOBALS['db_username'] = "root";
	}

	/* 2DO: in installer eintragen, wenn nicht schon sowieso */
	if (!isset($GLOBALS['db_password'])) {
		$GLOBALS["db_password"] = trim(fgets(fopen("/etc/dbpw", 'r')));
	}


	try {
		$GLOBALS['dbh'] = mysqli_connect($GLOBALS['db_host'], $GLOBALS['db_username'], $GLOBALS['db_password'], $GLOBALS['db_name']);
	} catch (\Throwable $e) {
		print("aaaaaa$e\aaaaaa");
		print("!!!!".mysqli_connect_errno()."!!!!");
		if (mysqli_connect_errno()) {
			die("Failed to connect to MySQL" . mysqli_connect_error());
		}
	}

	function generateRandomString($length = 10) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

	$user_id = hash("sha256", $_SERVER['REMOTE_ADDR'] ?? generateRandomString(100));
	if(array_key_exists("annotate_userid", $_COOKIE) && preg_match("/^([a-f0-9]{64})$/", $_COOKIE["annotate_userid"])) {
		$user_id = $_COOKIE["annotate_userid"];
	} else {
		setcookie("annotate_userid", $user_id);
	}

	function dier($msg) {
		print "<pre>";
		print_r($msg);
		print "</pre>";

		print "<pre>";
		debug_print_backtrace();
		print "</pre>";

		exit(1);
	}

	function number_of_annotations ($uid, $img) {
		$img = hash("sha256", $img);
		$dir = "annotations/$img/$uid/";

		if(is_dir($dir)) {
			$files = scandir($dir);

			$i = 0;

			foreach($files as $file) {
				if(preg_match("/\.json$/", $file)) {
					$i++;
				}
			}

			return $i;
		}
		return 0;
	}

	function shuffle_assoc($my_array) {
		$keys = array_keys($my_array);

		shuffle($keys);

		foreach($keys as $key) {
			$new[$key] = $my_array[$key];
		}

		$my_array = $new;

		return $my_array;
	}

	function get_number_of_unannotated_imgs() {
		$q = "select count(*) from (select id from image where id not in (select image_id from annotation)) a";
		$r = rquery($q);

		$res = null;

		while ($row = mysqli_fetch_row($r)) {
			$res = $row[0];
		}

		return $res;
	}

	function get_number_of_annotated_imgs() {
		$q = "select count(*) from (select image_id from annotation group by image_id) a";
		$r = rquery($q);

		$res = null;

		while ($row = mysqli_fetch_row($r)) {
			$res = $row[0];
		}

		return $res;
	}

	function get_home_string () {
		$annotation_stat = get_number_of_annotated_imgs();
		$unannotation_stat = get_number_of_unannotated_imgs();

		$str = "Annotierte Bilder: ".htmlentities($annotation_stat ?? "");
		$str .= ", unannotierte Bilder: ".htmlentities($unannotation_stat ?? "");
		if($unannotation_stat != 0) {
			$str .= " (".htmlentities(sprintf("%.2f", $annotation_stat / ($annotation_stat + $unannotation_stat) * 100))."% annotiert)";
		}

		$str .= "<br><a href='overview.php'>Übersicht über meine eigenen annotierten Bilder</a>";

		return $str;
	}


	function get_current_tags () {
		$annos = [];

		$query = "select c.name, count(*) as anzahl from annotation a left join category c on c.id = a.category_id group by c.id order by anzahl desc";
		$res = rquery($query);

		while ($row = mysqli_fetch_row($res)) {
			$annos[$row[0]] = $row[1];
		}

		return $annos;
	}

	function get_json_cached ($path) {
		$tmp_dir = "tmp/__json_cache__/";
		$mtime = filemtime($path);

		$cache_file = md5($path.$mtime);

		$cache_path = "$tmp_dir$cache_file";

		$cache_key = hash("sha256", $cache_path);

		$data = json_decode(file_get_contents($path), true);
		return $data;


		/*
		if(!is_dir($tmp_dir)) {
			mkdir($tmp_dir);
		}

		$data = array();


		if(file_exists($cache_path)) {
			$data = unserialize(file_get_contents($cache_path));
		} else {
			$data = json_decode(file_get_contents($path), true);
			file_put_contents($cache_path, serialize($data));
		}

		return $data;
		 */
	}

	function mywarn ($msg) {
		file_put_contents('php://stderr', $msg);
	}

	function get_get ($name, $default = null) {
		if(isset($_GET[$name])) {
			return $_GET[$name];
		}
		return $default;
	}

	function rquery($query){
		$query_start_time = microtime(true);
		$result = mysqli_query($GLOBALS['dbh'], $query) or dier(array("query" => $query, "error" => mysqli_error($GLOBALS['dbh'])));
		$query_end_time = microtime(true);

		$GLOBALS["queries"][] = array(
			"query" => $query,
			"time" => ($query_end_time - $query_start_time)
		);

		return $result;
	}

	function my_mysqli_real_escape_string ($arg) {
		return mysqli_real_escape_string($GLOBALS['dbh'], $arg ?? "");
	}

	function esc ($parameter) { 
		if(!is_array($parameter)) { // Kein array
			if(isset($parameter) && strlen($parameter)) {
				return '"'.mysqli_real_escape_string($GLOBALS['dbh'], $parameter).'"';
			} else {
				return 'NULL';
			}
		} else { // Array
			$str = join(', ', array_map("esc", array_map('my_mysqli_real_escape_string', $parameter)));
			return $str;
		}
	}


	function get_or_create_category_id ($category) {
		$category = ltrim(rtrim($category));
		$select_query = "select id from category where name = ".esc($category);		
		$select_res = rquery($select_query);

		$res = null;

		while ($row = mysqli_fetch_row($select_res)) {
			$res = $row[0];
		}

		if(is_null($res)) {
			$insert_query = "insert into category (name) values (".esc($category).") on duplicate key update name = values(name)";
			rquery($insert_query);
			return get_or_create_category_id($category);
		} else {
			return $res;
		}
	}

	function get_or_create_user_id ($user) {
		$select_query = "select id from user where name = ".esc($user);		
		$select_res = rquery($select_query);

		$res = null;

		while ($row = mysqli_fetch_row($select_res)) {
			$res = $row[0];
		}

		#die($select_query);

		if(is_null($res)) {
			$insert_query = "insert into user (name) values (".esc($user).") on duplicate key update name = values(name)";
			rquery($insert_query);
			return get_or_create_user_id($user);
		} else {
			return $res;
		}
	}

	function get_image_width_and_height_from_file ($fn) {
		$imgsz = getimagesize("./images/".$fn);

		$width = $imgsz[0];
		$height = $imgsz[1];

		try {
			$exif = @exif_read_data("images/$file");
		} catch (\Throwable $e) {
			//;
		}

		if(isset($exif["Orientation"]) && $exif['Orientation'] == 6) {
			list($width, $height) = array($width, $height);
		}

		return array($width, $height);
	}

	function get_image_id ($image) {
		$select_query = "select id from image where filename = ".esc($image);		
		$select_res = rquery($select_query);

		$res = null;

		while ($row = mysqli_fetch_row($select_res)) {
			$res = $row[0];
		}

		return $res;
	}

	function get_or_create_image_id ($image, $width=null, $height=null) {
		$select_query = "select id from image where filename = ".esc($image);		
		$select_res = rquery($select_query);

		$res = null;

		while ($row = mysqli_fetch_row($select_res)) {
			$res = $row[0];
		}

		#die($select_query);

		if(is_null($res)) {
			$width_and_height = get_image_width_and_height_from_file($image);
			$width = $width_and_height[0];
			$height = $width_and_height[1];

			$insert_query = "insert into image (filename, width, height) values (".esc(array($image, $width, $height)).") on duplicate key update filename = values(filename), width = values(width), height = values(height)";
			rquery($insert_query);
			return get_or_create_image_id($image);
		} else {
			return $res;
		}
	}

	function get_image_height ($id) {
		$query = "select height from image where id = ".esc($id);
		$res = rquery($query);
		$r = "";

		while ($row = mysqli_fetch_row($res)) {
			$r = $row[0];
		}

		return $r;
	}

	function get_image_width ($id) {
		$query = "select width from image where id = ".esc($id);
		$res = rquery($query);
		$r = "";

		while ($row = mysqli_fetch_row($res)) {
			$r = $row[0];
		}

		return $r;
	}

	function parse_position ($str, $wimg, $himg) {
		if(preg_match("/xywh=pixel:(-?\d+)(?:\.\d+)?,(-?\d+)(?:\.\d+)?,(-?\d+)(?:\.\d+)?,(-?\d+)(?:\.\d+)?/", $str, $matches)) {
			$x = $matches[1] < 0 ? 0 : $matches[1];
			$y = $matches[2] < 0 ? 0 : $matches[2];
			$w = $matches[3] > $wimg ? $wimg : $matches[3];
			$h = $matches[4] > $himg ? $himg : $matches[4];

			return array(
				$x,
				$y,
				$w,
				$h
			);
		} else {
			return null;
		}
	}

	function create_annotation ($image_id, $user_id, $category_id, $x_start, $y_start, $w, $h, $json, $annotarius_id) {
		/*
		create table annotation (id int unsigned primary key auto_increment, user_id int unsigned, category_id int unsigned, x_start int unsigned, y_start int unsigned, w int unsigned, h int unsigned, json MEDIUMBLOB, foreign key (category_id) references category(id) on delete cascade, foreign key (user_id) references user(id) on delete cascade);
		 */

		$query = "insert into annotation (image_id, user_id, category_id, x_start, y_start, w, h, json, annotarius_id) values (".
			esc(array($image_id, $user_id, $category_id, $x_start, $y_start, $w, $h, $json, $annotarius_id)).
			") on duplicate key update image_id = values(image_id), category_id = values(category_id), x_start = values(x_start), y_start = values(y_start), w = values(w), h = values(h), json = values(json), annotarius_id = values(annotarius_id)";

		rquery($query);
	}

	#die(get_or_create_category_id("raketenspiraleaasd"));
	#die(get_or_create_category_id("\n\nDAS HIER SOLLTE KEINE NEWLINES raketenspiraleaasd\n\n"));
	#die(get_or_create_user_id("raketenspiraleasdadasdfff"));
	#die(get_or_create_image_id("blaaasdasd.jpg"));

	function flag_deleted ($annotarius_id) {
		$query = "update annotation set deleted = 1 where annotarius_id = ".esc($annotarius_id);

		rquery($query);
	}

	function get_next_random_unannotated_image () {
		$query = "select filename from image where id not in (select image_id from annotation) order by rand() limit 1";
		$res = rquery($query);

		$result = null;

		while ($row = mysqli_fetch_row($res)) {
			$result = $row[0];
		}

		return $result;
	}
?>
