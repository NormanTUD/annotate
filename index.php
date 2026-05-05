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

	if(file_exists("allow_local_training")) {
		// Auto-numerate the suggested model name
		$base_model_name = "auto_trained";
		$existing_models_res = rquery("SELECT model_name FROM models WHERE model_name LIKE " . esc($base_model_name . "%") . " GROUP BY model_name ORDER BY model_name ASC");
		$existing_model_names = [];
		while ($row = mysqli_fetch_row($existing_models_res)) {
			$existing_model_names[] = $row[0];
		}

		$next_number = 1;
		foreach ($existing_model_names as $existing_name) {
			if (preg_match('/^' . preg_quote($base_model_name, '/') . '_(\d+)$/', $existing_name, $m)) {
				$num = intval($m[1]);
				if ($num >= $next_number) {
					$next_number = $num + 1;
				}
			}
		}

		$suggested_model_name = $base_model_name . "_" . $next_number;
?>
		<div style="margin: 10px 0; padding: 12px 16px; background: #1e1e2e; border: 1px solid #333; border-radius: 8px; display: flex; flex-direction: column; gap: 12px;">
		    <form id="train_form" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 0;">
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
				<input type="text" name="model_name" value="<?php echo htmlspecialchars($suggested_model_name); ?>" placeholder="Name for saved model" style="background: #313244; color: #cdd6f4; border: 1px solid #45475a; border-radius: 4px; padding: 4px 8px; font-size: 13px;">
			</label>
			<button type="submit" id="train_submit_btn" style="background: #a6e3a1; color: #1e1e2e; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: background 0.2s;">
			    🚀 Train Model on Server
			</button>
		    </form>
		    <div id="train_output_container" style="display: none; background: #11111b; border: 1px solid #45475a; border-radius: 6px; padding: 12px; max-height: 500px; overflow-y: auto;">
			<pre id="train_output" style="margin: 0; white-space: pre-wrap; word-wrap: break-word; color: #cdd6f4; font-size: 12px;"></pre>
		    </div>
		</div>

		<script>
		document.getElementById('train_form').addEventListener('submit', function(e) {
		    e.preventDefault();
		    
		    var form = this;
		    var btn = document.getElementById('train_submit_btn');
		    var container = document.getElementById('train_output_container');
		    var output = document.getElementById('train_output');
		    
		    // Disable button and show output area
		    btn.disabled = true;
		    btn.textContent = '⏳ Training in progress...';
		    btn.style.background = '#f9e2af';
		    container.style.display = 'block';
		    output.textContent = 'Starting training...\n';
		    
		    // Build form data
		    var formData = new FormData(form);
		    
		    // Use fetch with streaming response
		    fetch('train_internal.php', {
			method: 'POST',
			body: formData
		    }).then(function(response) {
			var reader = response.body.getReader();
			var decoder = new TextDecoder();
			
			function read() {
			    return reader.read().then(function(result) {
				if (result.done) {
				    // Training finished - check if successful
				    if (output.textContent.indexOf('successfully trained and uploaded') !== -1 ||
				        output.textContent.indexOf('All done!') !== -1) {
					output.textContent += '\n\n✅ Training complete! Reloading page...\n';
					setTimeout(function() {
					    window.location.reload();
					}, 2000);
				    } else {
					btn.textContent = '🚀 Train Model on Server';
					btn.style.background = '#a6e3a1';
					btn.disabled = false;
				    }
				    return;
				}
				
				var text = decoder.decode(result.value, {stream: true});
				// Strip HTML tags but keep the text content
				var stripped = text.replace(/<[^>]*>/g, '');
				// Remove the initial padding spaces
				stripped = stripped.replace(/^\s{100,}/, '');
				output.textContent += stripped;
				
				// Auto-scroll to bottom
				container.scrollTop = container.scrollHeight;
				
				return read();
			    });
			}
			
			return read();
		    }).catch(function(err) {
			output.textContent += '\n\n❌ Error: ' + err.message + '\n';
			btn.textContent = '🚀 Train Model on Server';
			btn.style.background = '#a6e3a1';
			btn.disabled = false;
		    });
		});
		</script>
<?php
	}
?>

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
