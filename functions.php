<?php

	// alle warnings als fatal errors ausgeben
	function exception_error_handler($errno, $errstr, $errfile, $errline ) {
	    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
	}
	set_error_handler("exception_error_handler");
	ini_set('display_errors', '1');


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
		try {
			$GLOBALS['dbh'] = mysqli_connect($GLOBALS['db_host'], $GLOBALS['db_username'], $GLOBALS['db_password']);
			$handle = fopen("sql.txt", "r");
			if ($handle) {
				while (($line = fgets($handle)) !== false) {
					if(!preg_match("/^\s*$/", $line)) {
						rquery($line);
					}
				}

				fclose($handle);
			}
			print("<h1>DB created</h1><h1>Importing files...</h1>");

			import_files();
		} catch (\Throwable $e) {
			print("$e");
			print("!!!!".mysqli_connect_errno()."!!!!");
			if (mysqli_connect_errno()) {
				die("Failed to connect to MySQL" . mysqli_connect_error());
			}
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

	function shuffle_assoc($my_array) {
		$keys = array_keys($my_array);

		shuffle($keys);

		foreach($keys as $key) {
			$new[$key] = $my_array[$key];
		}

		$my_array = $new;

		return $my_array;
	}

	function get_number_of_not_completetly_imported () {
		$q = "select count(*) from image i left join image_data id on id.filename = i.filename where id.filename is null";
		$r = rquery($q);

		$res = null;

		while ($row = mysqli_fetch_row($r)) {
			$res = $row[0];
		}

		return $res;
	}

	function get_number_of_unidentifiable_imgs() {
		$q = "select count(*) from image where unidentifiable = 1";
		$r = rquery($q);

		$res = null;

		while ($row = mysqli_fetch_row($r)) {
			$res = $row[0];
		}

		return $res;
	}

	function get_number_of_offtopic_imgs () {
		$q = "select count(*) from image where offtopic = 1";
		$r = rquery($q);

		$res = null;

		while ($row = mysqli_fetch_row($r)) {
			$res = $row[0];
		}

		return $res;
	}

	function get_number_of_curated_imgs () {
		$q = "select count(*) from (select id from image where id in (select image_id from annotation where deleted = 0 and curated = 1) and deleted = 0) a";
		$r = rquery($q);

		$res = null;

		while ($row = mysqli_fetch_row($r)) {
			$res = $row[0];
		}

		return $res;
	}

	function get_number_of_unannotated_imgs() {
		$q = "select count(*) from image i left join annotation a on i.id = a.image_id where a.id is null and i.perception_hash is not null and i.deleted = 0 and i.offtopic = 0 and i.unidentifiable = 0";

		$r = rquery($q);

		$res = null;

		while ($row = mysqli_fetch_row($r)) {
			$res = $row[0];
		}

		return $res;
	}

	function get_number_of_annotated_imgs() {
		$q = 'select count(*) from (select image_id from annotation where image_id in (select id from image where deleted = "0" and offtopic = "0" and perception_hash is not null) group by image_id) a';
		$r = rquery($q);

		$res = null;

		while ($row = mysqli_fetch_row($r)) {
			$res = $row[0];
		}

		return $res;
	}

	function get_home_string () {
		$annotated_imgs = get_number_of_annotated_imgs();
		$unannotated_imgs = get_number_of_unannotated_imgs();
		$curated_imgs = get_number_of_curated_imgs();
		$offtopic_imgs = get_number_of_offtopic_imgs();
		$unidentifiable_imgs = get_number_of_unidentifiable_imgs();
		$not_completely_imported = get_number_of_not_completetly_imported();

		$annotation_stat_str = number_format($annotated_imgs, 0, ',', '.');
		$unannotated_imgs_str = number_format($unannotated_imgs, 0, ',', '.');
		$curated_imgs_str = number_format($curated_imgs, 0, ',', '.');
		$offtopic_imgs_str = number_format($offtopic_imgs, 0, ',', '.');
		$not_completely_imported_str = number_format($not_completely_imported, 0, ',', '.');
		$unidentifiable_imgs_str = number_format($unidentifiable_imgs, 0, ',', '.');

		$str = "Annotiert: ".htmlentities($annotation_stat_str ?? "");
		if($not_completely_imported != 0) {
			$str .= ", noch nicht vollständig importiert: ".htmlentities($not_completely_imported_str ?? "");
		}

		if($curated_imgs) {
			$str .= ", kuratiert: ".htmlentities($curated_imgs_str ?? "");
		}

		if($unannotated_imgs) {
			$str .= ", unannotiert: ".htmlentities($unannotated_imgs_str ?? "");
		}

		if($offtopic_imgs) {
			$str .= ", offtopic: ".htmlentities($offtopic_imgs_str ?? "");
		}

		if($unidentifiable_imgs) {
			$str .= ", nicht identifizierbar: ".htmlentities($unidentifiable_imgs_str ?? "");
		}

		$curated_percent = 0;
		if($annotated_imgs) {
			$curated_percent = ($curated_imgs / $annotated_imgs) * 100;
		}
		if($curated_percent) {
			$curated_percent = number_format($curated_percent, 3, ',', '.');
		}

		if($unannotated_imgs != 0) {
			$annotated_nr = $annotated_imgs / ($annotated_imgs + $unannotated_imgs) * 100;
			$annotated_str = $annotated_nr;
			if($annotated_nr) {
				$annotated_str = sprintf("%.2f", $annotated_nr);
			}
		
			if($curated_percent) {
				$str .= " (".htmlentities($annotated_str)."% annotiert, davon $curated_percent% kuratiert)";
			} else {
				$str .= " (".htmlentities($annotated_str)."% annotiert)";
			}
		}

		$str .= "<br><a href='index.php'>Home</a>, ";
		$str .= "<a target='_blank' href='stat.php'>Statistik</a>, ";
		$str .= "<a target='_blank' href='upload_model.php'>Upload model</a>, ";
		$str .= "<a target='_blank' href='upload.php'>Upload</a>, ";
		$str .= "<a target='_blank' href='export_annotations_gui.php'>Annotationen exportieren</a>";

		return $str;
	}


	function get_current_tags ($only_uncurated=0, $group_by_perception_hash=0) {
		$annos = [];

		$query = "select name, anzahl from (select c.name, count(*) as anzahl from annotation a left join category c on c.id = a.category_id left join image i on a.image_id = i.id where i.deleted = 0 and a.deleted = 0 ";
		if($only_uncurated) {
			$query .= " and a.curated is null ";
		}
		$query .= " group by c.id order by anzahl desc, c.name asc) a";
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

	function get_image_width_and_height_from_file ($path) {
		$width = null;
		$height = null;
		try {
			$imgsz = getimagesize($path);

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
		} catch (\Throwable $e) {
			mywarn("$e for file $path inside get_image_width_and_height_from_file");
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

	function get_perception_hash_from_db ($filename) {
		$query = "select perception_hash from image where filename = ".esc($filename);
		$res = rquery($query);

		while ($row = mysqli_fetch_row($res)) {
			return $row[0];
		}

		return "";
	}

	function get_or_create_perception_hash_from_db ($path, $filename) {
		$old_perception_hash = get_perception_hash_from_db($filename);

		$new_perception_hash = $old_perception_hash;

		if($old_perception_hash == "") {
			$new_perception_hash = get_perception_hash($path);
		}

		$query = "update image set perception_hash = ".esc($new_perception_hash)." where filename = ".esc($filename);
		$res = rquery($query);

		if(!$res) {
			dier("Error setting perception hash");
			return "";
		}

		return $new_perception_hash;
	}

	function get_perception_hash ($path) {
		$command = 'python3 -c "import sys; import imagehash; from PIL import Image; file_path = sys.argv[1]; hash = str(imagehash.phash(Image.open(file_path).resize((512, 512)))); print(hash)" '.$path;

		ob_start();
		system($command);
		$hash = ob_get_clean();
		ob_flush();

		$hash = trim($hash);

		return $hash;
	}

	function get_or_create_image_id ($path, $filename, $perception_hash=null, $width=null, $height=null, $rec=0) {
		if($rec >= 10) {
			return;
		}
		$select_query = "select id from image where filename = ".esc($filename);		
		$select_res = rquery($select_query);

		$res = null;

		while ($row = mysqli_fetch_row($select_res)) {
			$res = $row[0];
		}

		#die($select_query);

		if(is_null($res)) {
			$width_and_height = get_image_width_and_height_from_file($path);
			if(!$width) {
				$width = $width_and_height[0];
			}

			if(!$height) {
				$height = $width_and_height[1];
			}

			if($width && $height) {
				$hash = $perception_hash;

				if(!$perception_hash) {
					$hash = get_or_create_perception_hash_from_db($path, $filename);
				}

				$insert_query = "insert into image (filename, width, height, perception_hash) values (".esc(array($filename, $width, $height, $hash)).") on duplicate key update filename = values(filename), width = values(width), height = values(height), deleted = 0, offtopic = 0";
				rquery($insert_query);
				return get_or_create_image_id($path, $filename, $perception_hash, $width, $height, $rec + 1);
			} else {
				return null;
			}
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
			") on duplicate key update image_id = values(image_id), category_id = values(category_id), x_start = values(x_start), y_start = values(y_start), w = values(w), h = values(h), json = values(json), annotarius_id = values(annotarius_id), deleted = 0";

		rquery($query);

		return $GLOBALS["dbh"]->insert_id;
	}

	#die(get_or_create_category_id("raketenspiraleaasd"));
	#die(get_or_create_category_id("\n\nDAS HIER SOLLTE KEINE NEWLINES raketenspiraleaasd\n\n"));
	#die(get_or_create_user_id("raketenspiraleasdadasdfff"));

	function mark_as_curated ($image_id) {
		$query = "update annotation set curated = 1 where image_id = ".esc($image_id);

		rquery($query);
	}

	function flag_all_annos_as_deleted ($image_id) {
		#$query = "update annotation set deleted = 1 where image_id = ".esc($image_id);
		$query = "delete from annotation where image_id = ".esc($image_id);

		rquery($query);
	}

	function delete_image_by_fn ($fn) {
		#$query = "update annotation set deleted = 1 where annotarius_id = ".esc($annotarius_id);
		$query = "delete from image where filename = ".esc($fn);

		rquery($query);
	}

	function flag_deleted ($annotarius_id) {
		#$query = "update annotation set deleted = 1 where annotarius_id = ".esc($annotarius_id);
		$query = "delete from annotation where annotarius_id = ".esc($annotarius_id);

		rquery($query);
	}

	function get_next_random_unannotated_image ($fn = "") {
		$query = 'select * from (select i.filename from image i left join image_data id on id.filename = i.filename left join annotation a on i.id = a.image_id where a.id is null and i.perception_hash is not null';
		if($fn) {
			$query .= " and i.filename like ".esc("%$fn%");
		}

		$query .= " and id.filename is not null ";
		$query .= ' order by rand()) a';

		$res = rquery($query);

		$result = null;

		while ($row = mysqli_fetch_row($res)) {
			$result = $row[0];
			return $result;
		}

		return null;
	}

	function get_image_data_id ($file) {
		$query = "select id from image_data where filename = ".esc($file);

		$res = rquery($query);

		while ($row = mysqli_fetch_row($res)) {
			return $row[0];
		}

		return null;
	}

	function import_files () {
		ini_set('memory_limit', '4096M');
		ini_set('max_execution_time', '300');
		set_time_limit(300);

		function shutdown() {
			mywarn("Exiting, rolling back changes\n");
			rquery("ROLLBACK;");
			rquery("SET autocommit=1;");
		}

		register_shutdown_function('shutdown');

		print "Importing images...<br>";

		$base_dir = "images";

		$files = scandir($base_dir);

		shuffle($files);

		$i = 0;
		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file)) {
				$is_in_images_table = is_null(get_image_id($file)) ? 1 : 0;
				$is_in_image_data_table = is_null(get_image_data_id($file)) ? 1 : 0;
				if(!$is_in_images_table || !$is_in_image_data_table) {
					rquery("SET autocommit=0;");
					rquery("START TRANSACTION;");
					$path = "$base_dir/$file";
					$image_id = insert_image_into_db($path, $file);

					if(!$image_id) {
						rquery("ROLLBACK;");
						rquery("SET autocommit=1;");

						dier("Could not get image id for $path / $file");
					}

					rquery("COMMIT;");
					rquery("SET autocommit=1;");

					print "Id for $file: ".$image_id."<br>\n";
					ob_flush();
					flush();
				}
			}

		}

		print "Done importing";

		exit(0);
	}

	function move_to_offtopic ($fn) {
		if(!preg_match("/\.\./", $fn) && preg_match("/\.jpg/", $fn)) {
			rquery("update image set offtopic = 1 where filename = ".esc($fn));
			rquery("update image set deleted = 1 where filename = ".esc($fn));
			print "Moved to offtopic";
		}
	}

	function move_to_unidentifiable ($fn) {
		if(!preg_match("/\.\./", $fn) && preg_match("/\.jpg/", $fn)) {
			rquery("update image set unidentifiable = 1 where filename = ".esc($fn));
			rquery("update image set deleted = 1 where filename = ".esc($fn));
			print "Moved from unidentifiable";
		}
	}

	function move_from_offtopic ($fn) {
		if(!preg_match("/\.\./", $fn) && preg_match("/\.jpg/", $fn)) {
			rquery("update image set offtopic = 0 where filename = ".esc($fn));
			rquery("update image set deleted = 0 where filename = ".esc($fn));
			print "Moved from offtopic";
		}
	}

	function get_base_url () {
		if(!$_SERVER["REQUEST_SCHEME"]) {
			die("REQUEST_SCHEME not in request");
		}

		if(!$_SERVER["SERVER_NAME"]) {
			die("SERVER_NAME not in request");
		}

		if(!$_SERVER["SERVER_PORT"]) {
			die("SERVER_PORT not in request");
		}

		if(!$_SERVER["PHP_SELF"]) {
			die("PHP_SELF not in request");
		}

		$scheme = $_SERVER["REQUEST_SCHEME"];
		$server_name = $_SERVER["SERVER_NAME"];
		$server_port = $_SERVER["SERVER_PORT"];
		$php_self = $_SERVER["PHP_SELF"];

		if(($scheme == "https" && $server_port == 443) || ($scheme == "http" && $server_port == 80)) {
			$b = $scheme."://".$server_name."/".$php_self;
		} else {
			$b = $scheme."://".$server_name.":".$server_port."/".$php_self;
		}
		$b = preg_replace("/\/[^\/]*?$/", "/", $b);
		$b = preg_replace("/([^:])\/\//", "$1/", $b);

		return $b;
	}

	function insert_image_into_db($file_tmp, $filename) {
		try {
			$is_in_image_data_table = is_null(get_image_data_id($filename)) ? 1 : 0;

			$existing_perception_hash = get_perception_hash_from_db($filename);
			$new_perception_hash = get_perception_hash($file_tmp);

			if($existing_perception_hash == $new_perception_hash && !$is_in_image_data_table) {
				return get_image_id($filename);
			}

			// Establish a database connection (replace with your actual database details)
			$pdo = new PDO("mysql:host=".$GLOBALS["db_host"].";dbname=".$GLOBALS["db_name"], $GLOBALS["db_username"], $GLOBALS["db_password"]);

			// Generate a unique filename to avoid conflicts
			$unique_filename = generate_unique_filename($pdo, $filename);

			$file_contents = file_get_contents($file_tmp);

			// Insert the unique filename into the database
			$stmt = $pdo->prepare("INSERT INTO image_data (filename, image_content) VALUES (:filename, :image_content)");
			$stmt->bindParam(':filename', $filename);
			$stmt->bindParam(':image_content', $file_contents, PDO::PARAM_LOB);
			$stmt->execute();

			// Close the database connection
			$pdo = null;

			$image_id = get_or_create_image_id($file_tmp, $filename);

			// Return the unique filename for display
			return $image_id;
		} catch (\Throwable $e) {
			// Log and handle the database error
			error_log("Database error: " . $e->getMessage());
			dier("Error: Unable to insert image into the database.");
		}
	}

	function generate_unique_filename($pdo, $filename) {
		$base_name = pathinfo($filename, PATHINFO_FILENAME);
		$extension = pathinfo($filename, PATHINFO_EXTENSION);
		$unique_filename = $base_name . '_' . uniqid() . '.' . $extension;

		// Check if the generated filename already exists in the database
		$stmt = $pdo->prepare("SELECT filename FROM image_data WHERE filename = :filename");
		$stmt->bindParam(':filename', $unique_filename);
		$stmt->execute();

		if ($stmt->rowCount() > 0) {
			// If it exists, recursively generate a new unique filename
			return generate_unique_filename($pdo, $filename);
		}

		return $unique_filename;
	}

	function print_image($fn) {
		$query = "select image_content from image_data where filename = ".esc($fn);

		$res = rquery($query);

		while ($row = mysqli_fetch_row($res)) {
			header('Content-Type: image/jpeg');
			print $row[0];
			exit(0);
		}

		print "Image <b>".htmlentities($fn)."</b> not found";
		exit(0);
	}

	function get_param ($name, $default = 0) {
		$res = $default;
		if(get_get($name)) {
			$res = intval(get_get($name));
		}
		return $res;
	}

	function get_list_of_models () {
		$query = 'select if(model_name is null or model_name = "", uid, model_name) as model_name, uid from models group by uid order by upload_time desc';

		$res = rquery($query);

		$models = [];
		while ($row = mysqli_fetch_row($res)) {
			$models[] = $row;
		}

		return $models;
	}

	function print_model_file ($uid, $filename) {
		$query = "select file_contents from models where uid = ".esc($uid)." and filename = ".esc($filename)."";

		$res = rquery($query);

		$models = [];
		while ($row = mysqli_fetch_row($res)) {
			print $row[0];
			exit(0);
		}
		
		print "uid <b>>$uid<</b>, filename <b>>$filename<</b> not found.<br>";
		exit(1);
	}

	function process_is_running ($process) {
		$res = proc_get_status($process);

		return $res["running"];
	}

	function insert_model_into_db ($model_name, $files_array) {
		try {
			// Establish a database connection (replace with your actual database details)
			$pdo = new PDO("mysql:host=".$GLOBALS["db_host"].";dbname=".$GLOBALS["db_name"], $GLOBALS["db_username"], $GLOBALS["db_password"]);

			// Initialize an array to store the IDs of inserted models
			$inserted_model_ids = [];

			$prefix = "model_";
			$uid = uniqid($prefix);

			// Loop through the files array
			foreach ($files_array as $path) {
				// Generate a unique filename to avoid conflicts
				$file = $path;
				$file = preg_replace("/.*\//", "", $file);
				$file_contents = file_get_contents($path);

				// Insert the model into the database
				$stmt = $pdo->prepare("INSERT INTO models (model_name, upload_time, filename, file_contents, uid) VALUES (:model_name, now(), :filename, :file_contents, :uid)");
				$stmt->bindParam(':model_name', $model_name);
				$stmt->bindParam(':filename', $file);
				$stmt->bindParam(':file_contents', $file_contents, PDO::PARAM_LOB);
				$stmt->bindParam(':uid', $uid);
				$stmt->execute();

				// Retrieve the ID of the inserted model
				$model_id = $pdo->lastInsertId();

				echo "ID for file $file: $model_id<br>";

				// Store the ID in the array
				$inserted_model_ids[] = $model_id;
			}

			// Close the database connection
			$pdo = null;

			return $inserted_model_ids;
		} catch (\Throwable $e) {
			// Log and handle the database error
			error_log("Database error: " . $e->getMessage());
			die("Error: Unable to insert models into the database.<br>".$e->getMessage());
		}
	}



	$GLOBALS["base_url"] = get_base_url();
?>
