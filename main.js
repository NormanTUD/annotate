"use strict";

const log = console.log;

const startQueryString = window.location.search;
const startUrlParams = new URLSearchParams(startQueryString);

var conf = 0.3;
var enable_debug = false;

var autonext_param = startUrlParams.get('autonext');
var model;
var last_load_dynamic_content = false;
var running_ki = false;
var tags = [];
var last_model_md5 = "";
var last_detected_names = [];

function uuidv4() {
	return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
		(c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
	);
}

async function load_model () {
	if(!has_model()) {
		console.info("Model doesnt exist. Not loading.");
		return;
	}

	var new_model_md5 = $("#chosen_model").val();

	if(model && new_model_md5 == last_model_md5) {
		return;
	}

	last_model_md5 = new_model_md5;

	if(model) {
		await tf.dispose(model);
	}

	var model_uid = $("#chosen_model").val();
	var model_json_url = "models/" + model_uid + "/model.json";

	model = await tf.loadGraphModel(
		model_json_url,
		{
			onProgress: function (p) {
				var percent = p * 100;
				percent = percent.toFixed(0);
				success("Loading Model", percent + "%<br>\n");
			}
		}
	);

	log("load_model done");
	await tf.setBackend('wasm');
	log("set wasm");

	await tf.ready();

	$("#loader").hide();
	$("#upload_button").show();
}

function print_home () {
	$.ajax({
		url: "print_home.php",
		type: "GET",
		dataType: "html",
		success: function (data) {
			$('#tab_home_top').html("");
			$('#tab_home_top').html(data);
		},
		error: function (xhr, status) {
			error("Error loading the home ribbon", "Sorry, there was a problem!");
		}
	});
}

function memory_debugger () {
	if($("#tab_home_top").html() == "") {
		print_home();
	}

	try {
		var mem = tf.memory();
		var num_tensors = mem.numTensors;
		var num_bytes = mem.numBytes;
		var num_mb = num_bytes / (1024 ** 2);
		num_mb = num_mb.toFixed(2);

		var str = "";
		if(num_tensors) {
			str = `<br>Memory: ${num_mb}MB (${num_tensors} tensors)`;
		}

		$("#memory_debugger").html(str);
	} catch (e) {
		$("#memory_debugger").html("");
	}
}

var anno;
var previous = [];

function refresh(){
	window.location.reload("Refresh")
}

function move_to_offtopic () {
	move_file("move_to_offtopic");
}

function move_from_offtopic () {
	move_file("move_from_offtopic");
}

function move_to_unidentifiable () {
	move_file("move_to_unidentifiable");
}

function move_file (to) {
	var image = $("#image")[0].src.replace(/.*filename=/, "");

	$.ajax({
		url: "move.php?" + to + "=" + image,
		type: "get",
		success: async function (response) {
			success("OK", response)
			await load_dynamic_content();

			await load_next_random_image();
		},
		error: async function(jqXHR, textStatus, errorThrown) {
			error("Error: " + textStatus, errorThrown);
			await load_dynamic_content();
		}
	})
}

function info(title, msg) {
	if(msg) {
		$("#status_bar").html("<span style='color: white'>" + title + ": " + msg + "</span>");
	} else {
		$("#status_bar").html("<span style='color: white'>" + title + "</span>");
	}
}

function success (title, msg) {
	if(msg) {
		$("#status_bar").html("<span style='color: white'>" + title + ": " + msg + "</span>");
	} else {
		$("#status_bar").html("<span style='color: white'>" + title + "</span>");
	}
}

function warn(title, msg) {
	console.warn(title);
	if(msg) {
		$("#status_bar").html("<span style='color: orange'>" + title + ": " + msg + "</span>");
	} else {
		$("#status_bar").html("<span style='color: orange'>" + title + "</span>");
		console.warn(msg);
	}
}

function error (title, msg) {
	$("#status_bar").html("<span style='color: red'>" + title + ": " + msg + "</span>");
}

async function make_item_anno(elem, widgets={}) {
	anno = await Annotorious.init({
		image: elem,
		widgets: widgets
	});

	await anno.loadAnnotations('get_current_annotations.php?first_other=1&source=' + elem.src.replace(/.*?filename=/, ""));

	// Add event handlers using .on
	anno.on('createAnnotation', function(annotation) {
		// Do something
		var data = {
			"position": annotation.target.selector.value,
			"body": annotation.body,
			"id": annotation.id,
			"source": annotation.target.source.replace(/.*\//, ""),
			"full": JSON.stringify(annotation)
		};

		$.ajax({
			url: "submit.php",
			type: "post",
			data: data,
			success: async function (response) {
				success("Create Anno: OK", response);
				await load_dynamic_content();
			},
			error: async function(jqXHR, textStatus, errorThrown) {
				error("Create anno: " + textStatus, errorThrown);
				await load_dynamic_content();
			}
		});
	});

	anno.on('updateAnnotation', function(annotation) {
		var data = {
			"position": annotation.target.selector.value,
			"body": annotation.body,
			"id": annotation.id,
			"source": annotation.target.source.replace(/.*\//, ""),
			"full": JSON.stringify(annotation)
		};
		$.ajax({
			url: "submit.php",
			type: "post",
			data: data,
			success: async function (response) {
				success("Update Anno: OK", response)
				await load_dynamic_content();
			},
			error: async function(jqXHR, textStatus, errorThrown) {
				error("Update anno: " + textStatus, errorThrown);
				await load_dynamic_content();
			}
		});
	});


	anno.on('deleteAnnotation', function(annotation) {
		var data = {
			"position": annotation.target.selector.value,
			"body": annotation.body,
			"id": annotation.id,
			"source": annotation.target.source.replace(/.*\//, ""),
			"full": JSON.stringify(annotation)
		};
		$.ajax({
			url: "delete_annotation.php",
			type: "post",
			data: data,
			success: async function (response) {
				success("Delete Anno: OK", response)
				await load_dynamic_content();
			},
			error: async function(jqXHR, textStatus, errorThrown) {
				error("delete Anno: " + textStatus, errorThrown);
				await load_dynamic_content();
			}
		});
	});

	anno.on('cancelSelected', function(selection) {
		log(selection);
	})

	if(!(await anno.getAnnotations().length)) {
		await predictImageWithModel();
	}
}

async function create_annos () {
	var items = $(".images");
	for (var i = 0; i < items.length; i++) {
		await make_item_anno(items[i]);
	}
}

function open_tab(evt, cityName) {
	var i, tabcontent, tablinks;

	tabcontent = document.getElementsByClassName("tabcontent");
	for (i = 0; i < tabcontent.length; i++) {
		tabcontent[i].style.display = "none";
	}

	tablinks = document.getElementsByClassName("tablinks");
	for (i = 0; i < tablinks.length; i++) {
		tablinks[i].className = tablinks[i].className.replace(" active", "");
	}

	document.getElementById(cityName).style.display = "block";
	evt.currentTarget.className += " active";
}

function toc () {
	var toc = "";
	var level = 0;

	document.getElementById("contents").innerHTML =
		document.getElementById("contents").innerHTML.replace(
			/<h([\d])>([^<]+)<\/h([\d])>/gi,
			function (str, openLevel, titleText, closeLevel) {
				if (openLevel != closeLevel) {
					return str;
				}

				if (openLevel > level) {
					toc += (new Array(openLevel - level + 1)).join("<ul>");
				} else if (openLevel < level) {
					toc += (new Array(level - openLevel + 1)).join("</ul>");
				}

				level = parseInt(openLevel);

				var anchor = titleText.replace(/ /g, "_");
				toc += "<li><a href=\"#" + anchor + "\">" + titleText
					+ "</a></li>";

				return "<h" + openLevel + "><a name=\"" + anchor + "\">"
					+ titleText + "</a></h" + closeLevel + ">";
			}
		);

	if (level) {
		toc += (new Array(level + 1)).join("</ul>");
	}

	document.getElementById("toc").innerHTML += toc;
}

const toDataURL = url => fetch(url)
	.then(response => response.blob())
	.then(blob => new Promise((resolve, reject) => {
		const reader = new FileReader()
		reader.onloadend = () => resolve(reader.result.split(',')[1])
		reader.onerror = reject
		reader.readAsDataURL(blob)
	}));

async function save_anno (annotation) {
	var data = {
		"position": annotation.target.selector.value,
		"body": annotation.body,
		"id": annotation.id,
		"source": annotation.target.source.replace(/.*\//, ""),
		"full": JSON.stringify(annotation)
	};

	if(enable_debug) {
		log("save anno data:", data);
	}

	$.ajax({
		url: "submit.php",
		type: "post",
		data: data,
		success: async function (response) {
			success("Save Anno: OK", response);
			await load_dynamic_content();
		},
		error: async function(jqXHR, textStatus, errorThrown) {
			error(textStatus, errorThrown);
			await load_dynamic_content();
		}
	});
}

function get_names_from_ki_anno (anno) {
	var names = [];
	for (var i = 0; i < anno.length; i++) {
		for (var k = 0; k < anno[i].body.length; k++) {
			names.push(anno[i]["body"][k]["value"]);
		}
	}

	var sorted = names.sort();
	//var uniq = [...new Set(names)];
	
	var counts = {};
	for (var i = 0; i < sorted.length; i++) {
		counts[sorted[i]] = 1 + (counts[sorted[i]] || 0);
	}

	var uniq = {};

	var keys = Object.keys(counts);
	for (var i = 0; i < keys.length; i++) {
		uniq[keys[i]] = counts[keys[i]];
	}

	return uniq;
}

function getUrlParam(name, default_value) {
	var params = new URLSearchParams(window.location.search);
	var val = parseFloat(params.get(name));
	return isNaN(val) ? default_value : val;
}

function show_spinner(msg) {
	let overlay = document.getElementById("ai_spinner_overlay");
	if (!overlay) {
		overlay = document.createElement("div");
		overlay.id = "ai_spinner_overlay";
		Object.assign(overlay.style, {
			position: "fixed",
			top: 0,
			left: 0,
			width: "100%",
			height: "100%",
			background: "rgba(0, 0, 0, 0.7)",
			color: "white",
			display: "flex",
			flexDirection: "column",
			alignItems: "center",
			justifyContent: "center",
			zIndex: 9999,
			fontSize: "1.5em",
			textAlign: "center"
		});

		const spinner = document.createElement("div");
		spinner.className = "spinner";
		Object.assign(spinner.style, {
			border: "12px solid #f3f3f3",
			borderTop: "12px solid #3498db",
			borderRadius: "50%",
			width: "80px",
			height: "80px",
			animation: "spin 1s linear infinite",
			marginBottom: "20px"
		});
		overlay.appendChild(spinner);

		const msgElem = document.createElement("div");
		msgElem.id = "ai_spinner_msg";
		msgElem.textContent = msg;
		overlay.appendChild(msgElem);

		document.body.appendChild(overlay);

		const style = document.createElement("style");
		style.textContent = `
			@keyframes spin {
				0% { transform: rotate(0deg); }
				100% { transform: rotate(360deg); }
			}`;
		document.head.appendChild(style);
	} else {
		const msgElem = document.getElementById("ai_spinner_msg");
		if (msgElem) msgElem.textContent = msg;
	}
}

function hide_spinner() {
	const overlay = document.getElementById("ai_spinner_overlay");
	if (overlay) {
		overlay.remove();
	}
}

function get_element() {
	const $image = $('#image');

	if($image.length) {
		return $image[0]
	}

	return null;
}

async function predictImageWithModel() {
	log("Starting prediction workflow...");

	const imageElement = await getValidImageElement();
	if (!imageElement) return;

	if (!await isModelReady()) return;

	await prepareUIForPrediction();

	const [modelWidth, modelHeight] = getModelInputShape();
	log(`Model input shape: width=${modelWidth}, height=${modelHeight}`);

	const predictionResult = await runPrediction(modelWidth, modelHeight);
	if (!predictionResult) return;

	const { boxes, scores, classes } = await extractDetectionData(predictionResult);
	log(`Detection data extracted: ${boxes.length} boxes`);

	await displayPredictionResults(boxes, scores, classes);

	await cleanupAfterPrediction();

	if (autonext_param) await loadNextRandomImageWithDelay();

	log("Prediction workflow finished.");
}

async function getValidImageElement() {
	const elem = get_element();
	if (!elem) {
		warn("#image not found");
		log("No image element found, aborting.");
	} else {
		log("Image element found.");
	}
	return elem;
}

async function isModelReady() {
	if (!await checkModelAvailable()) {
		warn("model not available");
		log("Model not available, aborting.");
		return false;
	}
	log("Model is available.");
	return true;
}

async function prepareUIForPrediction() {
	log("Preparing UI for prediction...");
	show_ai_stuff();
	running_ki = true;
	$("body").css("cursor", "progress");
	show_spinner("AI is being loaded...");
	await anno.clearAnnotations();
	log("Annotations cleared.");
	await load_model();
	log("Model loaded.");
	await tf.ready();
	log("TensorFlow ready.");
}

async function runPrediction(width, height) {
	try {
		log("Running prediction...");
		show_spinner("Prediction...");
		const res = await predict(width, height);
		$("body").css("cursor", "default");
		log(`Prediction completed. Shape: ${getShape(res)}`);
		if (enable_debug) log("Prediction result:", res);
		return res;
	} catch (e) {
		warn(e);
		log("Prediction failed with error:", e);
		$("body").css("cursor", "default");
		hide_spinner();
		running_ki = false;
		return null;
	}
}

async function extractDetectionData(predictionResult) {
	log("Processing model output...");
	const data = await processModelOutput(predictionResult);
	log(`Processed output: ${data.boxes.length} boxes`);
	return data;
}

async function displayPredictionResults(boxes, scores, classes) {
	log("Displaying prediction results...");
	show_spinner("Working on results...");
	await handleAnnotations(boxes, scores, classes);
	log("Results displayed.");
}

async function cleanupAfterPrediction() {
	log("Cleaning up after prediction...");
	running_ki = false;
	hide_spinner();
	log("Cleanup done.");
}

async function loadNextRandomImageWithDelay() {
	log("Loading next random image in 1.5s...");
	await sleep(1500);
	await load_next_random_image();
	log("Next image loaded.");
}

function getShape(arr) {
	const shape = [];
	while (Array.isArray(arr)) {
		shape.push(arr.length);
		arr = arr[0];
	}
	return shape;
}

// prüft, ob Model vorhanden ist, zeigt UI entsprechend an, gibt bool zurück
async function checkModelAvailable() {
	if (!await has_model()) {
		hide_ai_stuff();
		info("No AI model found. Not allowing predictImageWithModel stuff");
		return false;
	}
	return true;
}

// liefert Inputgröße des Models
function getModelInputShape() {
	return model.inputs[0].shape.slice(1, 3);
}

// führt die Model-Ausführung mit Bild-Tensor aus
async function predict(modelWidth, modelHeight) {
	tf.engine().startScope();

	const img_from_browser = tf.browser.fromPixels($("#image")[0]);
	const image_tensor = img_from_browser
		.resizeBilinear([modelWidth, modelHeight])
		.div(255)
		.expandDims();

	var res;

	try {
		res = await model.execute(image_tensor);

		try {
			res = res.arraySync();
		} catch (e) {
			// If cannot be converted is already float32array
		}

	} catch (e) {
		error("Exception: e", e)
	}

	tf.engine().endScope();

	return res;
}

function processModelOutput(res, confThreshold = 0.3, iouThreshold = 0.5) {
	log("processModelOutput: Starting...");

	const rawBoxes = [];
	const scores = [];
	const classes = [];

	const data = res[0];  // YOLOv11 Tensor: [features x predictions]
	const numPredictions = data[0].length;
	const numFeatures = data.length;

	log(`Raw data shape: ${numFeatures} features x ${numPredictions} predictions`);

	// Iteriere über alle Predictions
	for (let i = 0; i < numPredictions; i++) {
		const features = data.map(arr => arr[i]);
		const [x, y, w, h, ...classScores] = features;

		// Top Score und Klasse
		let bestScore = -Infinity;
		let bestClass = -1;
		for (let c = 0; c < classScores.length; c++) {
			if (classScores[c] > bestScore) {
				bestScore = classScores[c];
				bestClass = c;
			}
		}

		if (bestScore > confThreshold) {
			// Relative Koordinaten
			const relX = x / imgsz;
			const relY = y / imgsz;
			const relW = w / imgsz;
			const relH = h / imgsz;
			const x1 = relX - relW / 2;
			const y1 = relY - relH / 2;
			const x2 = relX + relW / 2;
			const y2 = relY + relH / 2;

			rawBoxes.push([x1, y1, x2, y2]);
			scores.push(bestScore);
			classes.push(bestClass);
		}
	}

	// --- Non-Maximum Suppression ---
	const keepBoxes = [];
	const keepScores = [];
	const keepClasses = [];

	const indices = scores.map((s, i) => i).sort((a, b) => scores[b] - scores[a]);

	while (indices.length > 0) {
		const i = indices.shift();
		keepBoxes.push(rawBoxes[i]);
		keepScores.push(scores[i]);
		keepClasses.push(classes[i]);

		for (let j = indices.length - 1; j >= 0; j--) {
			if (iou(rawBoxes[i], rawBoxes[indices[j]]) > iouThreshold) {
				indices.splice(j, 1);
			}
		}
	}

	log(`Processed ${keepBoxes.length} boxes after NMS`);

	// --- Umwandlung zurück zu [cx, cy, bw, bh] ---
	const finalBoxes = keepBoxes.map(b => {
		const cx = (b[0] + b[2]) / 2;
		const cy = (b[1] + b[3]) / 2;
		const bw = b[2] - b[0];
		const bh = b[3] - b[1];
		return [cx, cy, bw, bh];
	});

	finalBoxes.forEach((b, i) => {
		log(`Box ${i + 1}: class=${keepClasses[i]}, score=${keepScores[i].toFixed(3)}, cx,cy,bw,bh=[${b.map(v => v.toFixed(3)).join(', ')}]`);
	});

	return { boxes: finalBoxes, scores: keepScores, classes: keepClasses };
}

function iou(boxA, boxB) {
	const [x1a, y1a, x2a, y2a] = boxA;
	const [x1b, y1b, x2b, y2b] = boxB;

	const interX1 = Math.max(x1a, x1b);
	const interY1 = Math.max(y1a, y1b);
	const interX2 = Math.min(x2a, x2b);
	const interY2 = Math.min(y2a, y2b);

	const interArea = Math.max(0, interX2 - interX1) * Math.max(0, interY2 - interY1);
	const boxAArea = (x2a - x1a) * (y2a - y1a);
	const boxBArea = (x2b - x1b) * (y2b - y1b);

	return interArea / (boxAArea + boxBArea - interArea);
}

async function handleAnnotations(boxes, scores, classes) {
	if(enable_debug) {
		log("handleAnnotations:", "boxes:", boxes, "scores:", scores, "classes:", classes);
	}

	if (boxes.length === 0) {
		info("Nothing found", "Annotate manually");
		return;
	}

	delete_all_anno_current_image();

	const anno_boxes = [];

	for (let i = 0; i < boxes.length; i++) {
		const img_width = $("#image").width();
		const img_height = $("#image").height();


		const [cx, cy, bw, bh] = boxes[i];
		const x_min = cx - bw / 2;
		const x_max = cx + bw / 2;
		const y_min = cy - bh / 2;
		const y_max = cy + bh / 2;

		const x = Math.round(x_min * img_width);
		const y = Math.round(y_min * img_height);
		const w = Math.round((x_max - x_min) * img_width);
		const h = Math.round((y_max - y_min) * img_height);

		const this_class = classes[i];
		const this_score = scores[i];

		if (this_class === -1) {
			continue;
		}

		var this_labels = get_labels();

		if (Object.keys(get_labels()).length === 0) {
			error("ERROR", "has no labels");
			return;
		}

		const this_label = this_labels[this_class];

		if(enable_debug) {
			log(`this_label: ${this_label}, this_class: ${this_class}, x: ${x}, y: ${y}, h: ${h}, w: ${w}`);
		}

		if(this_label) {
			var anno_element = get_annotate_element(this_label, x, y, w, h);
			if(anno_element) {
				anno_boxes.push(anno_element);
			}
		} else {
			error("ERROR", `this_label was empty: ${this_label}`);
		}
	}

	success("Success", "Image Detection ran successfully");
	if(enable_debug) {
		log("anno_boxes", anno_boxes);
	}

	await anno.setAnnotations(anno_boxes);

	const new_annos = await anno.getAnnotations();
	for (const ann of new_annos) {
		await save_anno(ann);
	}

	success("Success", "Image Detection done.");
}

function get_labels() {
	return labels;
}

function get_annotate_element(this_label, x_start, y_start, w, h) {
	if (!Number.isInteger(x_start)) {
		error("get_annotate_element", `x_start (${x_start}) is not an integer`);
		return null;
	}
	if (!Number.isInteger(y_start)) {
		error("get_annotate_element", `y_start (${y_start}) is not an integer`);
		return null;
	}
	if (!Number.isInteger(w)) {
		error("get_annotate_element", `w (${w}) is not an integer`);
		return null;
	}
	if (!Number.isInteger(h)) {
		error("get_annotate_element", `h (${h}) is not an integer`);
		return null;
	}

	// Prüfen, ob alle Werte > 0 sind
	if (x_start < 0) {
		error("get_annotate_element", `x_start (${x_start}) must be >= 0`);
		return null;
	}
	if (y_start < 0) {
		error("get_annotate_element", `y_start (${y_start}) must be >= 0`);
		return null;
	}
	if (w <= 0) {
		error("get_annotate_element", `w (${w}) must be > 0`);
		return null;
	}
	if (h <= 0) {
		error("get_annotate_element", `h (${h}) must be > 0`);
		return null;
	}

	return {
		"type": "Annotation",
		"body": [
			{
				"type": "TextualBody",
				"value": this_label,
				"purpose": "tagging"
			}
		],
		"source": $("#image")[0].src,
		"target": {
			"source": $("#image")[0].src,
			"selector": {
				"type": "FragmentSelector",
				"conformsTo": "http://www.w3.org/TR/media-frags/",
				"value": `xywh=pixel:${x_start},${y_start},${w},${h}`
			}
		},
		"@context": "http://www.w3.org/ns/anno.jsonld",
		"id": "#" + uuidv4()
	}
}

function autonext () {
	if(autonext_param) {
		autonext_param = false;
		$("#autonext_img_button").text("AutoNext");
		$(".disable_in_autonext").prop("disabled", false);
	} else {
		autonext_param = true;
		load_next_random_image()
		$("#autonext_img_button").text("Stop AutoNext");
		$(".disable_in_autonext").prop("disabled", true);
	}
}

function sleep(ms) {
	return new Promise(resolve => setTimeout(resolve, ms));
}

async function create_selects_from_annotation(force = 0) {
	if ($(":focus").is("select") && !force) {
		return;
	}
	if (typeof anno !== "object") {
		return;
	}

	// Hole KI-Annotationen
	const ki_names = get_names_from_ki_anno(await anno.getAnnotations());
	const joined_ki_names = JSON.stringify(ki_names);

	if (last_detected_names !== joined_ki_names) {
		last_detected_names = joined_ki_names;

		if (Object.keys(ki_names).length) {
			let html = "";
			const selects = [];
			const ki_names_keys = Object.keys(ki_names);

			for (let i = 0; i < ki_names_keys.length; i++) {
				previous[i] = ki_names_keys[i];

				let this_select = `<select data-nr='${i}' class='ki_select_box'>`;
				let found = false;

				for (let j = 0; j < tags.length; j++) {
					if (ki_names_keys[i] === tags[j]) found = true;
					this_select += `<option ${ki_names_keys[i] === tags[j] ? "selected" : ""} value="${tags[j]}">${tags[j]}</option>`;
				}

				// Falls der aktuelle Name nicht in tags ist, füge ihn als eigene Option hinzu
				if (!found) {
					this_select += `<option selected value="${ki_names_keys[i]}">${ki_names_keys[i]}</option>`;
				}

				this_select += `</select> (${ki_names[ki_names_keys[i]]})`;
				selects.push(this_select);
			}

			html += selects.join(", ");

			if ($("#ki_detected_names").html() !== html) {
				$("#ki_detected_names").html(html);
			}

			$(".ki_select_box").off("change").on("change", async function (e) {
				const nr = $(this).data("nr");
				const old_value = previous[nr];
				const new_value = e.currentTarget.value;

				await set_all_current_annotations_from_to(old_value, new_value);
				await create_selects_from_annotation(1);

				previous[nr] = new_value;
			});

		} else {
			// Wenn keine KI-Namen vorhanden und KI gerade nicht läuft
			if (!running_ki) {
				if ($("#ki_detected_names").html() !== "") {
					$("#ki_detected_names").html("");
				}
			}
		}

		last_detected_names = joined_ki_names;
	}
}

async function set_all_current_annotations_from_to (from, name) {
	var current = await anno.getAnnotations();

	for (var i = 0; i < current.length; i++) {
		var old = current[i]["body"][0]["value"];
		if(from == old && old != name) {
			current[i]["body"][0]["value"] = name;
			success("changed " + old + " to " + name);
		}
	}

	await anno.setAnnotations(current);

	var new_annos = await anno.getAnnotations();
	for (var i = 0; i < new_annos.length; i++) {
		await save_anno(new_annos[i]);
	}

	await load_dynamic_content();
}

async function load_page() {
	if(typeof(anno) == "object") {
		await anno.destroy();
		anno = undefined;
	}

	await load_dynamic_content();

	await make_item_anno($("#image")[0], [
		{
			widget: 'TAG',
			vocabulary: tags
		}
	]);

	create_zoom_slider();
}

async function load_dynamic_content () {
	/*
	if((Date.now() - last_load_dynamic_content) <= 2000) {
		log("Not reloading dynamic content");
		return;
	}

	log("Reloading dynamic content");
	last_load_dynamic_content = Date.now()
	*/

	print_home();

	await $.ajax({
		url: "get_current_list.php?json=1",
		type: "GET",
		dataType: "html",
		success: function (data) {
			var d = JSON.parse(data);
			tags = Object.keys(d.tags);
			$('#list').html("");
			if(d.html != "<ul style='list-style: conic-gradient'></ul>") {
				$('#list').html(d.html);
			} else {
				$('#list').html("<i>No tags yet</i>");
			}
		},
		error: function (xhr, status) {
			error("Error loading the current list", "Sorry, there was a problem!");
		}
	});
}

function getNewURL(url, param, paramVal){
	var newAdditionalURL = "";
	var tempArray = url.split("?");
	var baseURL = tempArray[0];
	var additionalURL = tempArray[1];
	var temp = "";
	if (additionalURL) {
		tempArray = additionalURL.split("&");
		for (var i=0; i<tempArray.length; i++){
			if(tempArray[i].split('=')[0] != param){
				newAdditionalURL += temp + tempArray[i];
				temp = "&";
			}
		}
	}

	var rows_txt = temp + "" + param + "=" + encodeURIComponent(paramVal);
	return baseURL + "?" + newAdditionalURL + rows_txt;
}

function update_url_param(param, val) {
	window.history.replaceState('', '', getNewURL(window.location.href, param, val));
}

function set_image_url (img) {
	update_url_param("edit", img);
}

async function set_img_from_filename (fn) {
	set_image_url(fn);

	if(fn) {
		$("#annotation_area").show();
		$("#no_imgs_left").hide();

		if($("#ki_detected_names").html != "") {
			$("#ki_detected_names").html("");
		}
		if($("#filename").html() != fn) {
			$("#filename").html(fn);
		}
		$("#image").prop("src", "print_image.php?filename=" + encodeURIComponent(fn));

		await load_page();
	} else {
		$("#annotation_area").hide();
		$("#no_imgs_left").show();
	}
}

async function load_next_random_image(fn = false) {
	if (fn) {
		show_spinner("Loading image...");
		await set_img_from_filename(fn);
		hide_spinner();
	} else {
		let ajax_url = "get_random_unannotated_image.php";
		let queryString = window.location.search;
		let urlParams = new URLSearchParams(queryString);
		let like = urlParams.get('like');

		if (like) {
			ajax_url += "?like=" + encodeURIComponent(like);
		}

		show_spinner("Loading next image...");

		try {
			await $.ajax({
				url: ajax_url,
				type: "GET",
				dataType: "html",
				success: async function (fn) {
					await set_img_from_filename(fn);
				},
				error: function (xhr, status) {
					hide_spinner();
					error("Error loading the next image", "Sorry, there was a problem!");
				}
			});
		} catch (e) {
			hide_spinner();
			if (e && typeof e === "object" && "message" in e) {
				error("Error", e.message);
			} else {
				error("Error", "" + e);
			}
		}

		hide_spinner();
	}

	await load_dynamic_content();
}

document.onkeydown = function (e) {
	if($(":focus").length) {
		return;
	}

	e = e || window.event;
	switch (e.which) {
		case 85:
			move_to_unidentifiable();
			break;
		case 79:
			move_to_offtopic();
			break;
		case 78:
			load_next_random_image()
			break;
		case 75:
			predictImageWithModel();
			break;
		default:
			break;
	}
}

function curate_anno (image) {
	$.ajax({
		url: "curate_anno.php?image=" + image,
		type: "get",
		success: async function (response) {
			success("Curate Anno: OK", response)
			await load_dynamic_content();
		},
		error: async function(jqXHR, textStatus, errorThrown) {
			error("Curate Anno: " + textStatus, errorThrown);
			await load_dynamic_content();
		}
	});
}

function delete_all_anno_new_tab (image) {
	delete_all_anno(image);

	var url = "index.php?edit=" + image;

	window.open(url, '_blank').focus();
}

function delete_all_anno_current_image() {
	var image_filename = $("#image").attr("src").replace(/.*filename=/, "");
	if(image_filename) {
		delete_all_anno(image_filename);
	} else {
		error("delete_all_anno:", "Cannot find image");
	}
}

function delete_all_anno (image) {
	$.ajax({
		url: "delete_all_anno.php?image=" + image,
		type: "get",
		success: async function (response) {
			success("Delete Anno: OK", response)
			await load_dynamic_content();
		},
		error: async function(jqXHR, textStatus, errorThrown) {
			error("Delete Anno: " + textStatus, errorThrown);
			await load_dynamic_content();
		}
	});
}

async function has_model () {
	var res = 0;

	try {
		const response = await fetch('has_model.php');
		if (!response.ok) {
			throw new Error('Failed to fetch has_model.php');
		}

		const content = await response.text();
		const hasModelValue = content.includes('1') ? 1 : 0;

		if(!hasModelValue) {
			$("#autonext_img_button").text("AutoNext");
			autonext_param = false;
		}

		return hasModelValue;
	} catch (error) {
		// Handle errors, log, and return 0.
		warn('Error:', error.message);
		return 0;
	}
}

function hide_ai_stuff() {
	$(".ai_stuff").hide();
}

function show_ai_stuff () {
	$(".ai_stuff").show();

}

async function show_or_hide_ai_stuff () {
	if(await has_model()) {
		show_ai_stuff();
	} else {
		hide_ai_stuff();
	}
}

function start_like () {
	var like = $("#like").val();
	update_url_param("like", like);
	load_next_random_image()
}

setInterval(memory_debugger, 1000);
setInterval(create_selects_from_annotation, 1000);

$(document).ready(() => {
	show_or_hide_ai_stuff();

	success("Loaded site", "OK");
})

// --- Zoom / overlay sync — paste this and remove older zoom code ---
let zoom_factor = 1.0;

function update_overlay_and_image_size() {
  const img = document.getElementById("image");
  if (!img || !img.naturalWidth) return;

  // set displayed image size (this changes layout, so event coords stay correct)
  const newWidth = Math.round(img.naturalWidth * zoom_factor);
  img.style.width = newWidth + "px";
  img.style.height = "auto";

  // sync Annotorious SVG overlay if present
  const svg = document.querySelector(".a9s-annotationlayer");
  if (svg) {
    const newHeight = Math.round(img.naturalHeight * zoom_factor);
    svg.setAttribute("width", newWidth);
    svg.setAttribute("height", newHeight);
    svg.setAttribute("viewBox", `0 0 ${img.naturalWidth} ${img.naturalHeight}`);
    svg.style.width = newWidth + "px";
    svg.style.height = newHeight + "px";
    svg.style.transform = "none";
    svg.style.transformOrigin = "top left";
  }

  // make sure annotorious editor popups are not inversely scaled:
  document.querySelectorAll(".r6o-editor, .r6o-editor *").forEach(el => {
    el.style.transform = "none";
    el.style.transformOrigin = "top left";
    el.style.zoom = ""; // reset any previous hacks; keep native size
  });
}

function zoom_image(delta, anchor_x = null, anchor_y = null) {
  const container = document.getElementById("image_container");
  const img = document.getElementById("image");
  if (!img || !img.naturalWidth) return;

  // current displayed size BEFORE zoom
  const prevRect = img.getBoundingClientRect();
  const prevWidth = prevRect.width;
  const prevHeight = prevRect.height;

  const oldZoom = zoom_factor;
  zoom_factor *= delta > 0 ? 1.1 : 0.9;
  zoom_factor = Math.min(Math.max(zoom_factor, 0.25), 6);

  // compute cursor position relative to image (in page coordinates -> convert to image local)
  const imgRect = img.getBoundingClientRect();
  const cx = anchor_x !== null ? (anchor_x - imgRect.left) : (imgRect.width / 2);
  const cy = anchor_y !== null ? (anchor_y - imgRect.top) : (imgRect.height / 2);

  // store previous scroll
  const prevScrollLeft = container ? container.scrollLeft : 0;
  const prevScrollTop  = container ? container.scrollTop  : 0;

  // resize image + overlay (this updates layout)
  update_overlay_and_image_size();

  // new displayed size AFTER zoom
  const newRect = img.getBoundingClientRect();
  const newWidth = newRect.width;
  const newHeight = newRect.height;

  // compute new scroll so that the point under cursor stays under cursor
  if (container) {
    const ratioW = (prevWidth === 0) ? 1 : newWidth / prevWidth;
    const ratioH = (prevHeight === 0) ? 1 : newHeight / prevHeight;

    const newScrollLeft = (prevScrollLeft + cx) * ratioW - cx;
    const newScrollTop  = (prevScrollTop  + cy) * ratioH - cy;

    container.scrollLeft = Math.max(0, Math.round(newScrollLeft));
    container.scrollTop  = Math.max(0, Math.round(newScrollTop));
  }
}

$(document).on("wheel", function (e) {
  // Alt + wheel does zoom centered at cursor
  if (e.altKey) {
    e.preventDefault();
    const dir = e.originalEvent.deltaY < 0 ? 1 : -1;
    zoom_image(dir, e.clientX, e.clientY);
  }
});

// call after image loads (or when you set src) so overlay starts correct
function init_image_and_overlay_on_load() {
  const img = document.getElementById("image");
  if (!img) return;
  if (img.complete) update_overlay_and_image_size();
  img.onload = () => {
    update_overlay_and_image_size();
    // small timeout to let Annotorious position elements
    setTimeout(() => update_overlay_and_image_size(), 50);
  }
}

// creates a slider toolbar before #image_container
function create_zoom_slider() {
  // avoid double insertion
  if (document.getElementById('zoom_toolbar')) return;

  const container = document.getElementById('image_container');
  if (!container) return;

  const toolbar = document.createElement('div');
  toolbar.id = 'zoom_toolbar';
  toolbar.style.display = 'flex';
  toolbar.style.alignItems = 'center';
  toolbar.style.gap = '8px';
  toolbar.style.marginBottom = '6px';
  toolbar.style.userSelect = 'none';

  const label = document.createElement('span');
  label.textContent = 'Zoom:';
  label.style.fontSize = '0.9em';
  toolbar.appendChild(label);

  const input = document.createElement('input');
  input.type = 'range';
  input.min = -5;
  input.max = 5;
  input.step = 1;
  input.value = 0;
  input.id = 'zoom_slider';
  input.style.cursor = 'pointer';
  input.style.width = '200px';
  toolbar.appendChild(input);

  const val = document.createElement('span');
  val.id = 'zoom_value';
  val.textContent = '100%';
  val.style.minWidth = '60px';
  val.style.fontFamily = 'monospace';
  toolbar.appendChild(val);

  const resetBtn = document.createElement('button');
  resetBtn.type = 'button';
  resetBtn.textContent = 'Reset';
  resetBtn.style.marginLeft = '6px';
  toolbar.appendChild(resetBtn);

  // insert toolbar before the image container
  container.parentNode.insertBefore(toolbar, container);

  // map slider steps to zoom_factor: zoom_factor = 1.1 ** slider_value
  function slider_to_zoom(v) {
    return Math.pow(1.1, v);
  }

  function update_from_slider(v) {
    zoom_factor = slider_to_zoom(parseInt(v, 10));
    update_overlay_and_image_size();
    const pct = Math.round(zoom_factor * 100);
    document.getElementById('zoom_value').textContent = pct + '%';
  }

  // live update while dragging
  input.addEventListener('input', (ev) => {
    update_from_slider(ev.target.value);
  });

  // on release (desktop browsers fire 'change' on release), call the init re-sync
  input.addEventListener('change', (ev) => {
    update_from_slider(ev.target.value);
    // give layout a tiny moment then re-init annotorious sync
    setTimeout(() => init_image_and_overlay_on_load(), 50);
  });

  // also handle pointer/touch end cases: if user holds and releases outside the slider
  let pointerDown = false;
  input.addEventListener('pointerdown', () => { pointerDown = true; });
  window.addEventListener('pointerup', () => {
    if (pointerDown) {
      pointerDown = false;
      // call re-init once
      setTimeout(() => init_image_and_overlay_on_load(), 50);
    }
  });

  // reset button behaviour
  resetBtn.addEventListener('click', () => {
    input.value = 0;
    update_from_slider(0);
    setTimeout(() => init_image_and_overlay_on_load(), 50);
  });

  // initialize displayed value
  update_from_slider(input.value);
}

// call it once when document ready or when you create the image_container
// safe to call multiple times — it guards against duplicate toolbar creation
create_zoom_slider();
