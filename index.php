<?php
	include("header.php");
	include_once("functions.php");

	if(file_exists("/etc/import_annotate") || get_get("import")) {
		import_files();
	}

	$imgfile = "";
	if(isset($_GET["edit"])) {
		$imgfile = $_GET["edit"];
	} else {
		if(get_get("like")) {
			$imgfile = get_next_random_unannotated_image(get_get("like"));
		} else {
			$imgfile = get_next_random_unannotated_image();
		}
	}

	$number_annotated = get_number_of_annotated_imgs();

	$img_area_display = "none";
	$no_imgs_left_display = "none";

	if($imgfile) {
		$img_area_display = "block";
	}

	$img_data_id = get_image_data_id($imgfile);

	if(!$img_data_id) {
		$img_area_display = "none";
		$no_imgs_left_display = "block";
	}

	$available_models = get_list_of_models();
?>

	<div id="loader"></div>
	<table>
		<tr>
			<td style="vertical-align: baseline;">
				<div id="annotation_area" style="display: <?php print $img_area_display; ?>">
					<div id="content">
						<p>
							<button class="disable_in_autonext" id="next_img_button" onClick="load_next_random_image()">Next image</button>
							<!--<button class='ai_stuff' id="autonext_img_button" onClick="autonext()">AutoNext</button>-->
							<button class='disable_in_autonext ai_stuff' onclick="predictImageWithModel()">AI-Labelling</button>
							<button class="disable_in_autonext" onclick="move_to_offtopic()">Offtopic</button>
							<input class="disable_in_autonext" placeholder="Filename contains..." id="like" onchange="start_like()" />
<?php
							if(count($available_models)) {
								if(count($available_models) == 1) {
									print "<span style='visibility: none'>";
								}
								print "AI-Model: <select class='disable_in_autonext' onchange='load_model_and_predict()' id='chosen_model'>";
								print "<option selected value='none'>None</option>";

								$i = 0;
								foreach ($available_models as $_model) {
									$model_name = $_model[0];
									$model_uid = $_model[1];

									print "<option value='$model_uid'>$model_name</option>";
									$i++;
								}
								print "</select>";
								if(count($available_models) == 1) {
									print "</span>";
								}

								print "<br>";
							}
?>

						</p>
						<div id="image_container" style="position: relative; display: inline-block; transform-origin: top left;">
							<img id="image" />
						</div>
						<br>
						<div id="filename"></div>
						<div id="ki_detected_names"></div>
					</div>
				</div>

				<div id="no_imgs_left" style="display: <?php print $no_imgs_left_display; ?>">
<?php
					if(get_number_of_annotated_imgs()) {
?>
						All available images are annotated.
<?php
					} else {
?>
						No images are available.
<?php
					}
?>

					<a href="upload.php">Upload images</a> or check again later.
				</div>
			</td>
			<td>
				Current tags:

				<div id="list"></div>
			</td>
		</tr>
	</table>

	<script>
		load_dynamic_content();
<?php
		if($no_imgs_left_display == "none") {
?>
			load_next_random_image("<?php print $imgfile; ?>");
<?php
		}
?>
	</script>
<?php
	include_once("footer.php");
?>
