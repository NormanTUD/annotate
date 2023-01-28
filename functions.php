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

	function get_number_of_annotated_imgs() {
		$files = scandir("images");

		$annotated = 0;
		$not_annotated = 0;

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file)) {
				$file_hash = hash("sha256", $file);
				if(is_dir("annotations/$file_hash/")) {
					$annotated++;
				} else {
					$not_annotated++;
				}
			}
		}

		return [$annotated, $not_annotated];
	}

	function get_home_string () {
		$annotation_stat = get_number_of_annotated_imgs();

		$str = "Anzahl annotierter Bilder: ".htmlentities($annotation_stat[0] ?? "");
		$str .= ", Anzahl unannotierter Bilder: ".htmlentities($annotation_stat[1] ?? "");

		if($annotation_stat[1] != 0) {
			$percent = sprintf("%0.2f", ($annotation_stat[0] / ($annotation_stat[0] + $annotation_stat[1])) * 100);
			$str .= " ($percent%)";
		}

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
		if(is_array($GLOBALS["get_current_tags_cache"]) && count($GLOBALS["get_current_tags_cache"])) {
			return $GLOBALS["get_current_tags_cache"];
		}

		$files = scandir("images");

		$annos = [];

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file) && !preg_match("/^\.(?:\.)?$/", $file)) {
				$file_hash = hash("sha256", $file);
				$base_dir = "annotations/$file_hash/";
				if(is_dir($base_dir)) {
					$users = scandir("annotations/$file_hash/");
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
											#if(preg_match("/^\s*$/", $value)) {
											#	dier("$path is empty: >$value<");
											#}
											if(!array_key_exists($value, $annos)) {
												$annos[$value] = 1;
											} else {
												$annos[$value]++;
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}

		arsort($annos);

		$GLOBALS["get_current_tags_cache"] = $annos;

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

	#die(get_or_create_category_id("raketenspiraleaasd"));
	#die(get_or_create_user_id("raketenspiraleasdadasdfff"));
?>
