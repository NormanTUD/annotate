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
<div style="margin: 10px 0; padding: 12px 16px; background: #1e1e2e; border: 1px solid #333; border-radius: 8px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
    <form action="train_internal.php" method="POST" target="_blank" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 0;">
        <label style="color: #cdd6f4; font-size: 13px;">Model: 
            <select name="model" style="background: #313244; color: #cdd6f4; border: 1px solid #45475a; border-radius: 4px; padding: 4px 8px; font-size: 13px;">
                <option>yolo11n.yaml</option>
                <option>yolo11s.yaml</option>
                <option>yolo11m.yaml</option>
                <option>yolo11l.yaml</option>
                <option>yolo11x.yaml</option>
            </select>
        </label>
        <label style="color: #cdd6f4; font-size: 13px;">Epochs: 
            <input type="number" name="epochs" value="50" min="1" style="width: 70px; background: #313244; color: #cdd6f4; border: 1px solid #45475a; border-radius: 4px; padding: 4px 8px; font-size: 13px;">
        </label>
        <label style="color: #cdd6f4; font-size: 13px;">Model Name: 
            <input type="text" name="model_name" value="auto_trained" placeholder="Name for saved model" style="background: #313244; color: #cdd6f4; border: 1px solid #45475a; border-radius: 4px; padding: 4px 8px; font-size: 13px;">
        </label>
        <button type="submit" style="background: #a6e3a1; color: #1e1e2e; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: background 0.2s;">
            🚀 Train Model Internally
        </button>
    </form>
</div>

	<div id="loader"></div>
	<table>
		<tr>
			<td style="vertical-align: baseline;">
				<div id="annotation_area" style="display: <?php print $img_area_display; ?>">
					<button class="disable_in_autonext" style="background-color: red;" id="skip_img_button" onClick="skip_current_image()">
					    Skip
					</button>

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
								print "Model: <select class='disable_in_autonext' onchange='load_model_and_predict()' id='chosen_model'>";
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
							<canvas id="rotation_canvas" style="display:none;"></canvas>
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
			<td id="col_right">
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
