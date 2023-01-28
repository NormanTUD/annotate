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

	function number_of_annotations_total ($img) {
		$img = hash("sha256", $img);
		$imgdir = "annotations/$img/";
		$i = 0;
		if(is_dir($imgdir)) {
			$userdir = scandir($imgdir);
			foreach ($userdir as $k => $uid) {
				if($uid != "." && $uid != "..") {
					$dir = "annotations/$img/$uid/";

					if(is_dir($dir)) {
						$files = scandir($dir);

						foreach($files as $file) {
							if(preg_match("/\.json$/", $file)) {
								$i++;
							}
						}

					}
				}
			}
		}
		return $i;
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


	function img_has_annotation ($uid, $img) {
		$img = hash("sha256", $img);
		$dir = "annotations/$img/$uid/";

		$files = scandir($dir);

		foreach($files as $file) {
			if(preg_match("/\.json$/", $file)) {
				return true;
			}
		}

		return false;
	}

	function number_of_files_in_dir ($dir) {
		$filecount = count(glob($dir. "*/*"));
		return $filecount;
	}

	function nr_of_annotations ($img) {
		$img = hash("sha256", $img);
		$dir = "annotations/$img/";
		$res = number_of_files_in_dir($dir);
		return $res;
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
		$str .= " (".htmlentities(sprintf("%.2f", $annotation_stat / ($annotation_stat + $unannotation_stat) * 100))."% annotiert)";

		$str .= "<br><a href='overview.php'>Übersicht über meine eigenen annotierten Bilder</a>";

		return $str;
	}


	function print_header() {
		$annotation_stat = get_number_of_annotated_imgs();
?>
		Anzahl annotierter Bilder: <?php print htmlentities($annotation_stat[0] ?? ""); ?>, Anzahl unannotierter Bilder: <?php print htmlentities($annotation_stat[1] ?? ""); ?>
	<?php
			if($annotation_stat[1] != 0) {
				$percent = sprintf("%0.2f", ($annotation_stat[0] / ($annotation_stat[0] + $annotation_stat[1])) * 100);
				print " ($percent%)";
			}
	?>, <a href="overview.php">Übersicht über meine eigenen annotierten Bilder</a>, <a href="export_annotations.php">Annotationen exportieren</a>
		<br>
<?php
	}

	function get_current_tags () {
		$annos = [];

		$query = "select c.name, count(*) as anzahl from annotation a left join category c on c.id = a.category_id group by c.id";
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

	function image_has_tag($img, $tag) {
		$file_hash = hash("sha256", $img);
		$mdir = "annotations/$file_hash/";
		if(is_dir($mdir)) {
			$users = scandir($mdir);
			foreach($users as $a_user) {
				if(!preg_match("/^\.(?:\.)?$/", $a_user)) {
					$tdir = "annotations/$file_hash/$a_user/";
					$an = scandir($tdir);
					foreach($an as $a_file) {
						if(!preg_match("/^\.(?:\.)?$/", $a_file)) {
							$path = "$tdir/$a_file";
							$anno = get_json_cached($path);
							foreach ($anno["body"] as $item) {
								if($item["purpose"] == "tagging") {
									$value = strtolower($item["value"]);
									if($value == strtolower($tag)) {
										return true;
									}
								}
							}
						}
					}
				}
			}
		}
		return false;
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

	function get_random_unannotated_image () {
		$files = scandir("images");

		$img_files = array();

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file)) {
				$annotations = number_of_annotations_total($file);
				$img_files[$file] = $annotations;
			}
		}

		$img_files = shuffle_assoc($img_files);
		asort($img_files);

		$j = 0;
		$imgfile = "";
		foreach ($img_files as $f => $k) {
			if($j != 0) {
				continue;
			}
			$imgfile = $f;
			$j++;
		}

		return $imgfile;
	}

	function rquery($query){
		$query_start_time = microtime(true);
		$result = mysqli_query($GLOBALS['dbh'], $query) or die(mysqli_error($GLOBALS['dbh']));;
		$query_end_time = microtime(true);

		$GLOBALS["queries"][] = array(
			"query" => $query,
			"time" => ($query_end_time - $query_start_time)
		);

		return $result;
	}

	function my_mysqli_real_escape_string ($arg) {
		if(is_array($arg)) {
			dier($arg);
		}
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
			$str = join(', ', array_map('esc', array_map('my_mysqli_real_escape_string', $parameter)));
			return $str;
		}
	}


	function get_or_create_category_id ($category) {
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

	function parse_position ($str) {
		if(preg_match("/xywh=pixel:(\d+),(\d+),(\d+),(\d+)/", $str, $matches)) {
			return array($matches[1], $matches[2], $matches[3], $matches[4]);
		} else {
			return null;
		}
	}

	function create_annotation ($image_id, $user_id, $category_id, $x_start, $y_start, $x_end, $y_end, $json, $annotarius_id) {
		/*
		create table annotation (id int unsigned primary key auto_increment, user_id int unsigned, category_id int unsigned, x_start int unsigned, y_start int unsigned, x_end int unsigned, y_end int unsigned, json MEDIUMBLOB, foreign key (category_id) references category(id) on delete cascade, foreign key (user_id) references user(id) on delete cascade);
		*/

		$query = "insert into annotation (image_id, user_id, category_id, x_start, y_start, x_end, y_end, json, annotarius_id) values (".
			esc(array($image_id, $user_id, $category_id, $x_start, $y_start, $x_end, $y_end, $json, $annotarius_id)).
		") on duplicate key update image_id = values(image_id), category_id = values(category_id), x_start = values(x_start), y_start = values(y_start), x_end = values(x_end), y_end = values(y_end), json = values(json), annotarius_id = values(annotarius_id)";

		rquery($query);
	}

	#die(get_or_create_category_id("raketenspiraleaasd"));
	#die(get_or_create_user_id("raketenspiraleasdadasdfff"));
	#die(get_or_create_image_id("blaaasdasd.jpg"));

	function flag_deleted ($annotarius_id) {
		$query = "update annotation set deleted = 1 where annotarius_id = ".esc($annotarius_id);

		rquery($query);
	}
?>
