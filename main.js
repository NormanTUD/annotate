"use strict";

var zoom_input;
var zoom_factor = 1.0;
var used_model = null;
var disable_spinner = false;
var debouncing_time_rotation = 150;
let _load_dynamic_timeout = null;
let _load_dynamic_resolve_queue = [];
// --- Batching & debouncing for manual annotation events ---
let _annotation_save_queue = [];
let _annotation_save_timeout = null;
const _annotation_save_delay = 400; // ms to wait before flushing batch
let _watch_svg_timeout = null;
const _watch_svg_delay = 150;
let _selects_timeout = null;
const _selects_debounce_delay = 350;

let _annotation_dynamic_content_timeout = null;
const _annotation_dynamic_content_delay = 500;

// Fixed: Cache annotations with a short TTL
let _anno_cache = null;
let _anno_cache_time = 0;
const _anno_cache_ttl = 200; // ms

async function getCachedAnnotations(force_fresh = false) {
    const now = Date.now();
    if (!force_fresh && _anno_cache && (now - _anno_cache_time) < _anno_cache_ttl) {
        return _anno_cache;
    }
    if (typeof anno !== "object") return [];
    _anno_cache = await anno.getAnnotations();
    _anno_cache_time = now;
    return _anno_cache;
}

function invalidateAnnoCache() {
    _anno_cache = null;
    _anno_cache_time = 0;
}


async function flush_annotation_queue() {
    if (_annotation_save_queue.length === 0) return;

    const queue = _annotation_save_queue.splice(0);

    const to_delete = queue.filter(item => item._action === 'delete');
    const to_save = queue.filter(item => item._action !== 'delete');

    if (to_save.length > 0) {
        const batch = to_save.map(item => ({
            position: item.position,
            body: item.body,
            id: item.id,
            source: item.source,
            full: item.full,
            used_model: item.used_model || null
        }));

        try {
            const response = await $.ajax({
                url: "submit_batch.php",
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify({ annotations: batch }),
                dataType: "html"
            });
            success("Batch Save: OK", response);
        } catch (err) {
            error("Batch Save Failed", err.statusText || err);
        }
    }

    // Batch deletes too instead of one-by-one
    if (to_delete.length > 0) {
        try {
            await $.ajax({
                url: "delete_batch.php",
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify({
                    annotations: to_delete.map(item => ({
                        position: item.position,
                        body: item.body,
                        id: item.id,
                        source: item.source,
                        full: item.full
                    }))
                }),
                dataType: "html"
            });
        } catch (err) {
            error("Batch Delete Failed", err.statusText || err);
        }
    }

    invalidateAnnoCache();

    // Single debounced UI refresh — not three separate calls
    load_dynamic_content_debounced();
    create_selects_from_annotation_debounced(1);
    // Don't call watch_svg_auto here — it's called by update_overlay_and_image_size already
}

function queue_annotation_event(action, annotation) {
    _annotation_save_queue.push({
        _action: action,
        position: annotation.target.selector.value,
        body: annotation.body,
        id: annotation.id,
        source: annotation.target.source.replace(/.*\//, ""),
        full: JSON.stringify(annotation),
        used_model: used_model
    });

    // Reset the flush timer
    if (_annotation_save_timeout) {
        clearTimeout(_annotation_save_timeout);
    }

    _annotation_save_timeout = setTimeout(async () => {
        _annotation_save_timeout = null;
        await flush_annotation_queue();
    }, _annotation_save_delay);
}

function create_selects_from_annotation_debounced(force = 0) {
    if (_selects_timeout) {
        clearTimeout(_selects_timeout);
    }

    _selects_timeout = setTimeout(() => {
        _selects_timeout = null;
        create_selects_from_annotation(force);
    }, _selects_debounce_delay);
}

var skipped_images = [];

(function () {
	var style = document.createElement('style');
	style.textContent =
		'@keyframes glow{0%{box-shadow:0 0 4px #00ff88}50%{box-shadow:0 0 14px #00ff88}100%{box-shadow:0 0 4px #00ff88}}' +
		'@keyframes sweep{0%{transform:translateX(-100%) translateY(-100%) rotate(45deg)}100%{transform:translateX(150%) translateY(150%) rotate(45deg)}}' +
		'@keyframes noise{0%{opacity:0.10}50%{opacity:0.22}100%{opacity:0.10}}' +

		'.ai-analyzing{position:relative; overflow:hidden}' +

		'.ai-analyzing::before{' +
		'content:"";' +
		'position:absolute; inset:0;' +
		'background:repeating-linear-gradient(0deg,rgba(255,255,255,0.04) 0,rgba(255,255,255,0.04) 2px,rgba(0,0,0,0) 4px);' +
		'pointer-events:none;' +
		'animation:noise 1.2s infinite;' +
		'}' +

		'.ai-analyzing::after{' +
		'content:"";' +
		'position:absolute;' +
		'top:0; left:0; width:150%; height:150%;' +
		'background:linear-gradient(90deg,rgba(0,255,150,0) 0%,rgba(0,255,150,0.35) 50%,rgba(0,255,150,0) 100%);' +
		'pointer-events:none;' +
		'transform-origin:center;' +
		'animation:sweep 1.6s linear infinite;' +
		'}' +

		'@keyframes nothing_found_glow{' +
		'0%{box-shadow:0 0 4px rgba(255,0,0,0.3)}' +
		'50%{box-shadow:0 0 16px rgba(255,0,0,0.7)}' +
		'100%{box-shadow:0 0 4px rgba(255,0,0,0.3)}}' +

		'.nothing-found{position:relative; animation:nothing_found_glow 1s ease-in-out 1;}';

	document.head.appendChild(style);

	function get_wrapper() {
		return document.querySelector('div[style*="position: relative"]');
	}

	window.start_ai_animation = function () {
		var w = get_wrapper();
		if (!w) return;
		w.classList.add('ai-analyzing');
	};

	window.stop_ai_animation = function () {
		var w = get_wrapper();
		if (!w) return;
		w.classList.remove('ai-analyzing');
	};

	window.show_nothing_found_animation = function () {
		var w = get_wrapper();
		if (!w) return;
		w.classList.add('nothing-found');

		setTimeout(() => w.classList.remove('nothing-found'), 1000);
	};
})();

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

async function load_labels() {
	try {
		var labels_url = 'labels.php';
		const model_uuid = get_chosen_model_uuid();

		if(model_uuid) {
			labels_url = `${labels_url}?model_uuid=${model_uuid}`;
		}

		const response = await fetch(labels_url);
		if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
		const data = await response.json();
		if (!Array.isArray(data)) throw new Error("Response is not an array");
		labels = data;
	} catch (err) {
		console.error("Failed to load labels:", err);
	}
}

function get_chosen_model_uuid() {
	return $("#chosen_model").val();
}

async function load_model() {
	if (!has_model()) {
		console.info("Model doesn't exist. Not loading.");
		return;
	}

	if (model) {
		model.dispose();
		model = null;
	}

	const model_uuid = get_chosen_model_uuid();

	const new_model_md5 = model_uuid;
	if (model && new_model_md5 === last_model_md5) return;
	last_model_md5 = new_model_md5;

	if (model) {
		tf.disposeVariables();
		await tf.ready();
		model = null;
	}

	const model_json_url = "get_model_file.php?&uuid=" + encodeURIComponent(model_uuid) + "&filename=model.json";
	
	console.log(`Loading model_json_url: ${model_json_url}`);

	try {
		await tf.ready();

		console.log("Loading TFJS model from:", model_json_url);

		const resp = await fetch(model_json_url);
		const model_json = await resp.json();

		model = await tf.loadGraphModel(model_json_url, {
			onProgress: (p) => {
				const percent = (p * 100).toFixed(0);
				success("Loading Model", percent + "%<br>\n");
			}
		});

		log("load_model done");
		$("#loader").hide();
		$("#upload_button").show();
	} catch (e) {
		console.error("Model load failed:", e);
		if (e.stack) console.error(e.stack);

		if (e.message.includes("tensor should have")) {
			const match = e.message.match(/shape, \[([^\]]+)\], .* has (\d+)/);
			if (match) {
				console.error("Expected shape:", match[1], "but got values:", match[2]);
			}
		}

		error(`Error loading model: ${e}`);
	}
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

function render_status(color, title, msg) {
	var text = msg ? (title + ": " + msg) : title;

	$("#status_bar_msg").html("<span style='color:" + color + "'>" + text + "</span>")
}

function info(title, msg) {
	render_status("white", title, msg);
}

function success(title, msg) {
	render_status("white", title, msg);
}

function warn(title, msg) {
	console.warn(msg ? msg : title);
	render_status("orange", title, msg);
}

function error(title, msg) {
	console.error(msg ? msg : title);
	render_status("red", title, msg);
}

async function make_item_anno(elem, widgets = {}) {
    anno = await Annotorious.init({
        image: elem,
        widgets: widgets
    });

    await anno.loadAnnotations(
        'get_current_annotations.php?first_other=1&source=' +
        elem.src.replace(/.*?filename=/, "")
    );

    // --- createAnnotation: queue instead of immediate AJAX ---
    anno.on('createAnnotation', function (annotation) {
        queue_annotation_event('create', annotation);
    });

    // --- updateAnnotation: queue instead of immediate AJAX ---
    anno.on('updateAnnotation', function (annotation) {
        queue_annotation_event('update', annotation);
    });

    // --- deleteAnnotation: queue instead of immediate AJAX ---
    anno.on('deleteAnnotation', function (annotation) {
        queue_annotation_event('delete', annotation);
    });

    anno.on('cancelSelected', function (selection) {
        log(selection);
    });

    if (!(await anno.getAnnotations().length)) {
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

async function save_annos_batch(annotations) {
	if (!annotations || annotations.length === 0) return;

	const batch = annotations.map(annotation => ({
		position: annotation.target.selector.value,
		body: annotation.body,
		id: annotation.id,
		source: annotation.target.source.replace(/.*\//, ""),
			full: JSON.stringify(annotation),
			used_model: used_model
		}));

		if (enable_debug) {
			log("save_annos_batch data:", batch);
		}

		try {
			const response = await $.ajax({
				url: "submit_batch.php",
				type: "POST",
				contentType: "application/json",
				data: JSON.stringify({ annotations: batch }),
				dataType: "html"
			});
			success("Batch Save: OK", response);
		} catch (err) {
			error("Batch Save Failed", err.statusText || err);
		}
}

async function save_anno (annotation) {
	var data = {
		"position": annotation.target.selector.value,
		"body": annotation.body,
		"id": annotation.id,
		"source": annotation.target.source.replace(/.*\//, ""),
		"full": JSON.stringify(annotation),
		"used_model": used_model
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
	if(disable_spinner) {
		return;
	}

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

function get_image_element() {
	const $image = $('#image');

	if($image.length) {
		return $image[0]
	}

	return null;
}

async function load_model_and_predict () {
	blur_chosen_model();

	await load_labels();

	await predictImageWithModel();

}

async function predictImageWithModel() {
	if($("#chosen_model").length && $("#chosen_model").val().toLowerCase() == "none") {
		info("No AI model chosen");
		return;
	}
	
	used_model = $("#chosen_model").val();

	start_ai_animation();

	log("Starting prediction workflow...");

	const imageElement = await getValidImageElement();
	if (!imageElement) {
		stop_ai_animation();
		return;
	}

	if (!await isModelReady()) {
		stop_ai_animation();
		return;
	}

	await prepareUIForPrediction();

	const shape = getModelInputShape();

	if(shape) {
		const [modelWidth, modelHeight] = shape;
		log(`Model input shape: width=${modelWidth}, height=${modelHeight}`);

		const predictionResult = await runPrediction(modelWidth, modelHeight);
		if (!predictionResult) {
			stop_ai_animation();
			return;
		}

		const { boxes, scores, classes } = await processModelOutput(predictionResult, modelWidth, modelHeight);
		log(`Detection data extracted: ${boxes.length} boxes`);

		await handleAnnotations(boxes, scores, classes);

		await cleanupAfterPrediction();

		if (autonext_param) await loadNextRandomImageWithDelay();

		log("Prediction workflow finished.");
	} else {
		error("Not able to determine model shapes");
	}

	stop_ai_animation();
	
	used_model = null;
}

async function getValidImageElement() {
	const elem = get_image_element();
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
	if(anno) {
		await anno.clearAnnotations();
	}
	log("Annotations cleared.");
	await load_model();
	log("Model loaded.");
	await tf.ready();
	log("TensorFlow ready.");
}

async function runPrediction(width, height) {
	try {
		log("Running prediction...");
		const res = await predict(width, height);
		$("body").css("cursor", "default");
		log(`Prediction completed. Shape: ${getShape(res)}`);
		if (enable_debug) log("Prediction result:", res);
		return res;
	} catch (e) {
		warn(e);
		log("Prediction failed with error:", e);
		$("body").css("cursor", "default");
		running_ki = false;
		return null;
	}
}

async function cleanupAfterPrediction() {
	log("Cleaning up after prediction...");
	running_ki = false;
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

async function checkModelAvailable() {
	if (!await has_model()) {
		hide_ai_stuff();
		info("No AI model found. Not allowing predictImageWithModel stuff");
		return false;
	}
	return true;
}

function getModelInputShape() {
	return model?.inputs[0]?.shape?.slice(1, 3);
}

async function predict(modelWidth, modelHeight) {
	const image_tensor = tf.tidy(() => {
		return tf.browser.fromPixels($("#image")[0])
			.resizeBilinear([modelWidth, modelHeight])
			.div(255)
			.expandDims();
	});

	let res;
	try {
		res = await model.execute(image_tensor);
		const arr = res instanceof tf.Tensor ? res.arraySync() : res;
		return arr;
	} finally {
		image_tensor.dispose();
		if (res instanceof tf.Tensor) res.dispose();
	}
}

async function processModelOutput(res, modelWidth, modelHeight) {
	console.group("🔥 YOLO Post-Processing DEBUG");
	const startTime = performance.now();

	const confThreshold = conf || 0.3;
	const iouThreshold = 0.45;

	// *** 1. Determine shape directly from the raw model output ***
	// res is expected to be shaped [1, features, candidates] or [features, candidates]
	let rawTensor;

	if (Array.isArray(res) && Array.isArray(res[0]) && Array.isArray(res[0][0])) {
		// Shape: [1, F, C] — standard batch output
		rawTensor = tf.tensor3d(res);
	} else if (Array.isArray(res) && Array.isArray(res[0]) && !Array.isArray(res[0][0])) {
		// Shape: [F, C] — no batch dim
		rawTensor = tf.tensor2d(res).expandDims(0);
	} else if (res instanceof tf.Tensor) {
		rawTensor = res.rank === 2 ? res.expandDims(0) : res;
	} else {
		console.error("❌ Unexpected model output structure:", typeof res);
		console.groupEnd();
		return { boxes: [], scores: [], classes: [] };
	}

	// Now rawTensor is [1, F, C]
	const shape = rawTensor.shape;
	console.log(`Raw tensor shape: [${shape.join(', ')}]`);

	let FINAL_FEATURES = shape[1];
	let FINAL_CANDIDATES = shape[2];

	// YOLO outputs [1, 4+num_classes, num_candidates]
	// If features < candidates, the layout is [1, F, C] which is correct.
	// If features > candidates, the tensor might already be transposed [1, C, F].
	// We want the smaller dimension to be features (4 + num_classes).
	if (FINAL_FEATURES > FINAL_CANDIDATES) {
		console.log(`Swapping: features(${FINAL_FEATURES}) > candidates(${FINAL_CANDIDATES}), transposing.`);
		rawTensor = rawTensor.transpose([0, 2, 1]);
		[FINAL_FEATURES, FINAL_CANDIDATES] = [FINAL_CANDIDATES, FINAL_FEATURES];
	}

	const numClasses = FINAL_FEATURES - 4;

	if (numClasses <= 0) {
		console.error(`❌ Invalid number of classes: ${numClasses} (features=${FINAL_FEATURES})`);
		console.groupEnd();
		return { boxes: [], scores: [], classes: [] };
	}

	console.log(`Model-Input: ${modelWidth}x${modelHeight}`);
	console.log(`Shape: [Features: ${FINAL_FEATURES}, Candidates: ${FINAL_CANDIDATES}]`);
	console.log(`Detected classes: ${numClasses}, Labels loaded: ${labels.length}`);

	if (numClasses !== labels.length) {
		console.warn(`⚠️ Class count mismatch: model has ${numClasses} classes but ${labels.length} labels loaded. Using model's count.`);
	}

	// *** 2. Transpose to [1, candidates, features] for easier splitting ***
	let predictionsTensor = rawTensor.transpose([0, 2, 1]);
	console.log(`Tensor transposed. Shape: [${predictionsTensor.shape.join(', ')}]`);

	// *** 3. Split into boxes [4] and class scores [numClasses] ***
	const [rawBoxes, scores] = tf.split(predictionsTensor, [4, numClasses], 2);
	const boxes = rawBoxes.squeeze();
	const scoresSqueezed = scores.squeeze();
	console.log(`Split: Boxes[${boxes.shape.join(', ')}], Scores[${scoresSqueezed.shape.join(', ')}]`);

	// *** 4. Decode boxes and apply NMS ***
	const decodedBoxes = decodeYOLOBoxes(boxes, modelWidth, modelHeight);

	const [filteredBoxes, filteredScores, filteredClasses] = filterByConfidence(
		decodedBoxes,
		scoresSqueezed,
		confThreshold
	);

	const finalDetections = applyNMS(filteredBoxes, filteredScores, filteredClasses, iouThreshold);

	console.log(`Post-Processing done. Final Detections: ${finalDetections.length || Object.keys(finalDetections).length}`);
	console.groupEnd();

	// Clean up tensors
	rawTensor.dispose();
	predictionsTensor.dispose();
	rawBoxes.dispose();
	scores.dispose();
	boxes.dispose();
	scoresSqueezed.dispose();
	filteredBoxes.dispose();
	filteredScores.dispose();
	filteredClasses.dispose();
	decodedBoxes.dispose();

	return finalDetections;
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

function clamp(v, a, b) {
	if (isNaN(v)) return a;
	if (v < a) return a;
	if (v > b) return b;
	return v;
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

async function autonext () {
    const btn = $("#autonext_img_button");

    if (autonext_param) {
        autonext_param = false;
        btn.text("AutoNext");
        btn.removeClass("autonext-stop");
        $(".disable_in_autonext").prop("disabled", false);
    } else {
        autonext_param = true;
        btn.text("Stop AutoNext");
        btn.addClass("autonext-stop");
        $(".disable_in_autonext").prop("disabled", true);

        load_next_random_image();
        await sleep(1000);
    }
}

function sleep(ms) {
	return new Promise(resolve => setTimeout(resolve, ms));
}

async function create_selects_from_annotation(force = 0) {
    // Don't interrupt user interaction with selects
    if ($(":focus").is("select") && !force) {
        return;
    }
    if (typeof anno !== "object") {
        return;
    }

    const annotations = await anno.getAnnotations();

    // Build label counts in a single pass
    const label_counts = new Map();
    for (let i = 0; i < annotations.length; i++) {
        const bodies = annotations[i].body;
        for (let j = 0; j < bodies.length; j++) {
            const val = bodies[j].value;
            label_counts.set(val, (label_counts.get(val) || 0) + 1);
        }
    }

    // Fast-path: check if anything actually changed
    const cache_key = JSON.stringify(Array.from(label_counts.entries()).sort());
    if (cache_key === last_detected_names) {
        return;
    }
    last_detected_names = cache_key;

    const box = $("#ki_detected_names");

    if (label_counts.size === 0) {
        if (!running_ki && box.html() !== "") {
            box.html("");
        }
        return;
    }

    // Build all options HTML once (shared across selects)
    const tag_options = tags.map(t => `<option value="${t}">${t}</option>`);
    const tag_set = new Set(tags);

    // Build selects using array join (much faster than string concat in loops)
    const selects = [];
    let i = 0;

    for (const [label, count] of label_counts) {
        previous[i] = label;

        // Build options: mark the matching one as selected
        let options;
        if (tag_set.has(label)) {
            // Label exists in tags — rebuild with selected attribute
            options = tags.map(t =>
                `<option${t === label ? " selected" : ""} value="${t}">${t}</option>`
            ).join("");
        } else {
            // Label not in tags — append it as selected
            options = tag_options.join("") +
                `<option selected value="${label}">${label}</option>`;
        }

        selects.push(
            `<select data-nr='${i}' class='ki_select_box'>${options}</select> (${count})`
        );
        i++;
    }

    const html = selects.join(", ");

    // Only touch the DOM if content actually changed
    if (box.html() === html) {
        return;
    }

    box.html(html);

    // Use event delegation instead of binding per-element
    // Unbind previous delegated handler, rebind once
    box.off("change", ".ki_select_box").on("change", ".ki_select_box", async function (e) {
        const nr = $(this).data("nr");
        const old_value = previous[nr];
        const new_value = e.currentTarget.value;

        if (old_value !== new_value) {
            await set_all_current_annotations_from_to(old_value, new_value);
            create_selects_from_annotation_debounced(1);
            previous[nr] = new_value;
        }
    });
}

async function set_all_current_annotations_from_to(from, name) {
    const current = await getCachedAnnotations(true);
    let changed = false;

    for (let i = 0; i < current.length; i++) {
        for (let j = 0; j < current[i].body.length; j++) {
            if (current[i].body[j].value === from) {
                current[i].body[j].value = name;
                changed = true;
            }
        }
    }

    if (!changed) return;

    // Set annotations once — this is the expensive Annotorious call
    await anno.setAnnotations(current);
    invalidateAnnoCache();

    // Save what we already have in memory — don't re-fetch from Annotorious
    await save_annos_batch(current);

    // Single UI refresh, debounced
    load_dynamic_content_debounced();
}

let _load_dynamic_content_debounce_timeout = null;
function load_dynamic_content_debounced() {
    if (_load_dynamic_content_debounce_timeout) {
        clearTimeout(_load_dynamic_content_debounce_timeout);
    }
    _load_dynamic_content_debounce_timeout = setTimeout(() => {
        _load_dynamic_content_debounce_timeout = null;
        load_dynamic_content();
    }, 400);
}

async function remove_current_annos (fn) {
	const current_annos = await anno.getAnnotations();

	for (var i = 0; i < current_annos.length; i++) {
		anno.removeAnnotation(current_annos[i])
	}

	delete_all_anno(fn);
}

async function load_page() {
	if(typeof(anno) == "object") {
		await anno.destroy();
		anno = undefined;
	}

	create_zoom_slider();

	if($("#rotation_slider").length == 0) {
		create_rotation_slider();
	}

	await load_dynamic_content();

	await make_image_annotatable();
}

async function make_image_annotatable() {
	await make_item_anno($("#image")[0], [
		{
			widget: 'TAG',
			vocabulary: tags
		}
	]);
}

async function load_dynamic_content() {
	return new Promise(resolve => {
		_load_dynamic_resolve_queue.push(resolve);

		if (_load_dynamic_timeout) {
			clearTimeout(_load_dynamic_timeout);
		}

		_load_dynamic_timeout = setTimeout(async () => {
			_load_dynamic_timeout = null;

			print_home();

			try {
				await $.ajax({
					url: "get_current_list.php?json=1",
					type: "GET",
					dataType: "html",
					success: function (data) {
						var d = JSON.parse(data);
						tags = Object.keys(d.tags);
						$('#list').html("");
						if (d.html != "<ul style='list-style: conic-gradient'></ul>") {
							$('#list').html(d.html);
						} else {
							$('#list').html("<i>No tags yet</i>");
						}
					},
					error: function (xhr, status) {
						error("Error loading the current list", "Sorry, there was a problem!");
					}
				});
			} catch (e) {
				console.error("load_dynamic_content failed:", e);
			}

			// Resolve all callers that were waiting
			const queue = _load_dynamic_resolve_queue.splice(0);
			for (const r of queue) {
				r();
			}
		}, 300);
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
    var url = getNewURL(window.location.href, param, val);
    window.history.pushState({}, "", url);
}

function set_image_url (img) {
	update_url_param("edit", img);
}

window.addEventListener("popstate", function () {
	var params = new URLSearchParams(window.location.search);
	var img = params.get("edit");
	if (img) set_img_from_filename(img);
});

function fade_image_transition(fn) {
	return new Promise(function(resolve) {
		var img = $("#image");

		img.stop(true, true).css("opacity", 0);

		img.off("load").on("load", function() {
			var w = this.naturalWidth + "px";
			var h = this.naturalHeight + "px";

			$(this).css({
				width: w,
				height: h,
				opacity: 1
			});

			setTimeout(resolve, 200);
		});

		img.prop("src", "print_image.php?filename=" + encodeURIComponent(fn) + "&_=" + new Date().getTime());
	});
}

async function set_img_from_filename(fn, no_reset_zoom = false, reload = false, no_remove_rotation_toolbar = false) {
	if(!no_remove_rotation_toolbar) {
		$("#rotation_toolbar").remove();
	}

	if(!no_reset_zoom) {
		reset_zoom();
	}

	set_image_url(fn);

	if (!fn) {
		$("#annotation_area").hide();
		$("#no_imgs_left").show();
		watch_svg_auto_throttled();
		return;
	}

	$("#annotation_area").show();
	$("#no_imgs_left").hide();

	if ($("#ki_detected_names").html() !== "") {
		$("#ki_detected_names").html("");
	}

	if ($("#filename").html() !== fn || reload) {
		$("#filename").html(fn);
	}

	document.title = "annotate - " + fn;

	await fade_image_transition(fn);

	await load_page();

	watch_svg_auto_throttled();
}

async function load_next_random_image(fn = false) {
    if (fn) {
        await set_img_from_filename(fn);
    } else {
        let ajax_url = "get_random_unannotated_image.php";

        ajax_url += (ajax_url.includes("?") ? "&" : "?")
            + "skip=" + encodeURIComponent(JSON.stringify(skipped_images));

        let queryString = window.location.search;
        let urlParams = new URLSearchParams(queryString);
        let like = urlParams.get('like');

        if (like) {
            ajax_url += "?like=" + encodeURIComponent(like);
        }

        log("Loading next image...");

        try {
            await $.ajax({
                url: ajax_url,
                type: "GET",
                dataType: "html",
                success: async function (fn) {
                    await set_img_from_filename(fn);
                },
                error: function (xhr, status) {
                    error("Error loading the next image", "Sorry, there was a problem!");
                }
            });
        } catch (e) {
            if (e && typeof e === "object" && "message" in e) {
                error("Error", e.message);
            } else {
                error("Error", "" + e);
            }
        }

        log("Loaded next image");
    }

    await load_dynamic_content();

    create_selects_from_annotation_debounced(1);
}

document.onkeydown = function(e) {
	e = e || window.event;

	const target = e.target || e.srcElement;
	const is_typing_field = target.tagName === "INPUT" && target.type === "text"
		|| target.tagName === "TEXTAREA"
		|| target.isContentEditable;

	if (is_typing_field) return;

	switch (e.which) {
		case 79: // O
			move_to_offtopic();
			break;
		case 78: // N
			load_next_random_image();
			break;
		case 75: // K
			predictImageWithModel();
			break;
		case 83: // S
		    skip_current_image();
		    break;
		case 191:
		case e.key === "Escape":
			if (e.shiftKey) show_shortcut_help();
			break;

		default:
			break;
	}
};

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

	load_dynamic_content();	
}

function delete_all_anno_and_image(image) {
	delete_all_anno(image);
	$(image).remove();
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
	load_next_random_image();
}

$(document).ready(() => {
	load_labels();
	show_or_hide_ai_stuff();
})

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

	watch_svg_auto_throttled();
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

	watch_svg_auto_throttled();
}

function watch_svg_auto_throttled() {
    if (_watch_svg_timeout) return; // already scheduled, skip
    _watch_svg_timeout = setTimeout(() => {
        _watch_svg_timeout = null;
        watch_svg_auto();
    }, _watch_svg_delay);
}

function create_ai_threshold_sliders() {
	if (document.getElementById('ai_threshold_toolbar')) return;

	const container = document.getElementById('image_container');
	if (!container) return;

	const toolbar = document.createElement('div');
	toolbar.id = 'ai_threshold_toolbar';
	toolbar.style.display = 'flex';
	toolbar.style.alignItems = 'center';
	toolbar.style.gap = '14px';
	toolbar.style.marginBottom = '6px';
	toolbar.style.userSelect = 'none';

	function make_block(label_text, id, default_val) {
		const block = document.createElement('div');
		block.style.display = 'flex';
		block.style.alignItems = 'center';
		block.style.gap = '6px';

		const label = document.createElement('span');
		label.textContent = label_text;
		label.style.fontSize = '0.9em';

		const input = document.createElement('input');
		input.type = 'range';
		input.className = 'ai_stuff';
		input.min = 0;
		input.max = 1;
		input.step = 0.01;
		input.value = default_val;
		input.id = id;
		input.style.cursor = 'pointer';
		input.style.width = '160px';

		const val = document.createElement('span');
		val.id = id + '_value';
		val.style.minWidth = '48px';
		val.style.fontFamily = 'monospace';
		val.textContent = Number(default_val).toFixed(2);

		block.appendChild(label);
		block.appendChild(input);
		block.appendChild(val);

		return block;
	}

	const conf_block = make_block('Conf:', 'conf_slider', 0.3);
	const iou_block  = make_block('IoU:',  'iou_slider',  0.5);

	toolbar.appendChild(conf_block);
	toolbar.appendChild(iou_block);

	container.parentNode.insertBefore(toolbar, container);

	function update_display(id) {
		const slider = document.getElementById(id);
		const span   = document.getElementById(id + '_value');
		span.textContent = Number(slider.value).toFixed(2);
	}

	function attach_events(id) {
		const slider = document.getElementById(id);

		slider.addEventListener('input', () => update_display(id));
		slider.addEventListener('change', () => update_display(id));

		let down = false;
		slider.addEventListener('pointerdown', () => down = true);
		window.addEventListener('pointerup', () => {
			if (down) down = false;
		});
	}

	attach_events('conf_slider');
	attach_events('iou_slider');

	update_display('conf_slider');
	update_display('iou_slider');
}

function getConfThreshold() {
	const el = document.getElementById('conf_slider');
	return el ? parseFloat(el.value) : 0.3;
}

function getIouThreshold() {
	const el = document.getElementById('iou_slider');
	return el ? parseFloat(el.value) : 0.5;
}

function slider_to_zoom(v) {
	// map -5..5 exponentially so 0 = 1.0, ends are 0.1..5.0
	const min_zoom = 0.1;
	const max_zoom = 5.0;
	if (v === 0) return 1.0;
	const ratio = max_zoom / min_zoom;
	// normalize to 0..1
	const normalized = (parseFloat(v) + 5) / 10;
	return min_zoom * Math.pow(ratio, normalized);
}

function update_from_slider(v) {
	zoom_factor = slider_to_zoom(parseInt(v, 10));
	update_overlay_and_image_size();
	const pct = Math.round(zoom_factor * 100);
	document.getElementById('zoom_value').textContent = pct + '%';
}

function reset_zoom () {
	if(zoom_input) {
		zoom_input.value = 0;
		update_from_slider(0);
		setTimeout(() => init_image_and_overlay_on_load(), 50);
	}
}

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

	zoom_input = document.createElement('input');
	zoom_input.type = 'range';
	zoom_input.min = -5;
	zoom_input.max = 5;
	zoom_input.step = 0.1;
	zoom_input.value = 0;  // <— ensures 100 % default
	zoom_input.id = 'zoom_slider';
	zoom_input.style.cursor = 'pointer';
	zoom_input.style.width = '200px';
	toolbar.appendChild(zoom_input);

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
	resetBtn.onclick = function () {
		reset_zoom();
	};
	toolbar.appendChild(resetBtn);

	// insert toolbar before the image container
	container.parentNode.insertBefore(toolbar, container);

	// map slider steps to zoom_factor: zoom_factor = 1.1 ** slider_value
	// live update while dragging
	zoom_input.addEventListener('input', (ev) => {
		update_from_slider(ev.target.value);
	});

	// on release (desktop browsers fire 'change' on release), call the init re-sync
	zoom_input.addEventListener('change', (ev) => {
		update_from_slider(ev.target.value);
		// give layout a tiny moment then re-init annotorious sync
		setTimeout(() => init_image_and_overlay_on_load(), 50);
	});

	// also handle pointer/touch end cases: if user holds and releases outside the slider
	let pointerDown = false;
	zoom_input.addEventListener('pointerdown', () => { pointerDown = true; });
	window.addEventListener('pointerup', () => {
		if (pointerDown) {
			pointerDown = false;
			// call re-init once
			setTimeout(() => init_image_and_overlay_on_load(), 50);
		}
	});

	resetBtn.addEventListener('click', () => reset_zoom);

	// initialize displayed value
	update_from_slider(zoom_input.value);
}

function start_annotation_watch() {
	watch_svg('svg.a9s-annotationlayer', '#image');
}

function blur_chosen_model () {
	$("#chosen_model").blur();
}

async function create_rotation_slider() {
	const oldToolbar = document.getElementById('rotation_toolbar');
	if (oldToolbar) oldToolbar.remove();

	if (document.getElementById('rotation_toolbar')) return;

	const container = document.getElementById('image_container');
	if (!container) return;

	const params = new URLSearchParams(window.location.search);
	const fn = params.get("edit");
	if (!fn) return;

	const toolbar = document.createElement('div');
	toolbar.id = 'rotation_toolbar';
	toolbar.style.display = 'flex';
	toolbar.style.alignItems = 'center';
	toolbar.style.gap = '8px';
	toolbar.style.marginBottom = '6px';
	toolbar.style.userSelect = 'none';

	const label = document.createElement('span');
	label.textContent = 'Rotation:';
	label.style.fontSize = '0.9em';
	toolbar.appendChild(label);

	const rotation_input = document.createElement('input');
	rotation_input.type = 'range';
	rotation_input.min = 0;
	rotation_input.max = 360;
	rotation_input.step = 1;
	rotation_input.id = 'rotation_slider';
	rotation_input.style.cursor = 'pointer';
	rotation_input.style.width = '260px';
	toolbar.appendChild(rotation_input);

	const val = document.createElement('span');
	val.id = 'rotation_value';
	val.textContent = '0°';
	val.style.minWidth = '60px';
	val.style.fontFamily = 'monospace';
	toolbar.appendChild(val);

	const resetBtn = document.createElement('button');
	resetBtn.type = 'button';
	resetBtn.textContent = 'Reset';
	resetBtn.style.marginLeft = '6px';
	toolbar.appendChild(resetBtn);

	container.parentNode.insertBefore(toolbar, container);

	const btnMinus = document.createElement('button');
	btnMinus.type = 'button';
	btnMinus.textContent = '−1°';
	btnMinus.style.marginLeft = '6px';
	btnMinus.onclick = decrement_rotation;
	toolbar.appendChild(btnMinus);

	const btnPlus = document.createElement('button');
	btnPlus.type = 'button';
	btnPlus.textContent = '+1°';
	btnPlus.style.marginLeft = '6px';
	btnPlus.onclick = increment_rotation;
	toolbar.appendChild(btnPlus);

	const img = document.getElementById('image');

	const canvas = document.getElementById('rotation_canvas');
	const ctx = canvas.getContext('2d');

	// Originalbild für Canvas
	let orig_img = new Image();

	const unrotated_url = `print_image.php?filename=${encodeURIComponent(fn)}&rotation=0&_=${Date.now()}`;
	orig_img.src = unrotated_url;

	await new Promise(res => { orig_img.onload = res; });

	function renderRotation(deg) {
		const rad = deg * Math.PI / 180;
		const w = orig_img.width;
		const h = orig_img.height;

		const sin = Math.abs(Math.sin(rad));
		const cos = Math.abs(Math.cos(rad));
		const newW = Math.ceil(w * cos + h * sin);
		const newH = Math.ceil(w * sin + h * cos);

		canvas.width = newW;
		canvas.height = newH;

		ctx.fillStyle = 'rgb(128,128,128)'; // grauer Hintergrund
		ctx.fillRect(0, 0, newW, newH);

		ctx.save();
		ctx.translate(newW / 2, newH / 2);
		ctx.rotate(rad);
		ctx.drawImage(orig_img, -w / 2, -h / 2);
		ctx.restore();
	}

	let current_rotation = 0;
	try {
		const res = await fetch(`get_image_rotation.php?filename=${encodeURIComponent(fn)}`);
		const j = await res.json();
		if (j.ok) {
			current_rotation = parseInt(j.rotation);
			console.debug(`Setting current_rotation to ${current_rotation}`)
			rotation_input.value = current_rotation;
			val.textContent = current_rotation + "°";
			renderRotation(current_rotation);
		}
	} catch (e) {
		console.warn("Could not load initial rotation", e);
	}

	let save_timeout = null;

	rotation_input.addEventListener('input', (ev) => {
		remove_current_annos(fn); // await not possible here

		const rot = parseInt(ev.target.value);
		val.textContent = rot + "°";

		canvas.style.display = 'block';
		img.style.display = 'none';

		renderRotation(rot);
	});

	async function save_rotation(rot) {
		show_spinner("Saving rotated image");
		let url = `save_image_rotation.php?filename=${encodeURIComponent(fn)}&rotation=${rot}`;

		try {
			let response = await fetch(url, { cache: "no-store" });
			let result   = await response.json();

			if (!response.ok || !result.ok) {
				throw new Error(result.error || "Unknown server error");
			}

			current_rotation = result.rotation;

			await set_img_from_filename(fn, true, true, true);

			canvas.style.display = "none";
			img.style.display    = "block";

			info(`✔ Rotation saved for ${fn}: ${result.rotation}°`);

		} catch (e) {
			error(`✖ Rotation failed: ${e.message}`);
		}

		hide_spinner();
	}

	rotation_input.addEventListener('change', (ev) => {
		remove_current_annos(fn); // await not possible here

		const rot = parseInt(ev.target.value);
		if (save_timeout) clearTimeout(save_timeout);
		save_timeout = setTimeout(() => save_rotation(rot), debouncing_time_rotation);
	});

	resetBtn.onclick = async function () {
		rotation_input.value = 0;
		val.textContent = "0°";
		renderRotation(0);
		canvas.style.display = 'block';
		img.style.display = 'none';
		save_rotation(0);
	};
}

async function add_or_subtract_rotation(_val = 1) {
	let slider = document.getElementById("rotation_slider");
	let current = parseInt(slider.value) || 0;

	let min = parseInt(slider.min);
	let max = parseInt(slider.max);

	let next = current + _val;

	if (next < min) next = min;
	if (next > max) next = max;

	slider.value = next;

	disable_spinner = true;
	slider.dispatchEvent(new Event("input", { bubbles: true }));
	slider.dispatchEvent(new Event("change", { bubbles: true }));

	await sleep(debouncing_time_rotation + 100);

	disable_spinner = false;
}

async function increment_rotation() {
	await add_or_subtract_rotation(1);
}

async function decrement_rotation () {
	await add_or_subtract_rotation(-1);
}




/**
 * Decodes YOLO bounding boxes from model output.
 * YOLO outputs [cx, cy, w, h] in pixel space (0..modelWidth/Height).
 * We convert to [xMin, yMin, xMax, yMax] normalized to [0, 1].
 */
function decodeYOLOBoxes(boxes, modelWidth, modelHeight) {
	return tf.tidy(() => {
		const [cx, cy, w, h] = tf.split(boxes, 4, 1);

		console.log("=== decodeYOLOBoxes DEBUG ===");

		// Sample first 3 raw values
		const cxArr = cx.squeeze().arraySync();
		const cyArr = cy.squeeze().arraySync();
		const wArr = w.squeeze().arraySync();
		const hArr = h.squeeze().arraySync();

		for (let i = 0; i < Math.min(3, cxArr.length); i++) {
			console.log(`  Raw box[${i}]: cx=${cxArr[i].toFixed(4)}, cy=${cyArr[i].toFixed(4)}, w=${wArr[i].toFixed(4)}, h=${hArr[i].toFixed(4)}`);
		}

		// Check if values are already in pixel space or normalized
		const maxCx = Math.max(...cxArr.slice(0, 100));
		const maxCy = Math.max(...cyArr.slice(0, 100));
		const maxW = Math.max(...wArr.slice(0, 100));
		const maxH = Math.max(...hArr.slice(0, 100));
		console.log(`  Max values (first 100): cx=${maxCx.toFixed(4)}, cy=${maxCy.toFixed(4)}, w=${maxW.toFixed(4)}, h=${maxH.toFixed(4)}`);
		console.log(`  Model dimensions: ${modelWidth}x${modelHeight}`);

		const isPixelSpace = maxCx > 2.0 || maxCy > 2.0; // If values > 2, they're likely pixel coords
		console.log(`  Values appear to be in ${isPixelSpace ? 'PIXEL' : 'NORMALIZED'} space`);

		let xMin, yMin, xMax, yMax;

		if (isPixelSpace) {
			// Values are in pixel space (0..modelWidth), normalize to 0..1
			console.log("  -> Normalizing from pixel space to [0,1]");
			xMin = cx.sub(w.div(2)).div(modelWidth);
			yMin = cy.sub(h.div(2)).div(modelHeight);
			xMax = cx.add(w.div(2)).div(modelWidth);
			yMax = cy.add(h.div(2)).div(modelHeight);
		} else {
			// Values are already normalized (0..1)
			console.log("  -> Already normalized, converting cx,cy,w,h to xMin,yMin,xMax,yMax");
			xMin = cx.sub(w.div(2));
			yMin = cy.sub(h.div(2));
			xMax = cx.add(w.div(2));
			yMax = cy.add(h.div(2));
		}

		const result = tf.concat([xMin, yMin, xMax, yMax], 1);

		// Sample first 3 decoded values
		const resultArr = result.arraySync();
		for (let i = 0; i < Math.min(3, resultArr.length); i++) {
			console.log(`  Decoded box[${i}]: xMin=${resultArr[i][0].toFixed(6)}, yMin=${resultArr[i][1].toFixed(6)}, xMax=${resultArr[i][2].toFixed(6)}, yMax=${resultArr[i][3].toFixed(6)}`);
		}
		console.log("=== END decodeYOLOBoxes DEBUG ===");

		return result;
	});
}

/**
 * Filters boxes, scores, and classes by confidence threshold.
 */
function filterByConfidence(boxes, scores, confThreshold) {
	console.log("=== filterByConfidence DEBUG ===");
	console.log(`  Input boxes shape: [${boxes.shape}]`);
	console.log(`  Input scores shape: [${scores.shape}]`);
	console.log(`  Confidence threshold: ${confThreshold}`);

	const maxScores = scores.max(1);
	const classIndices = scores.argMax(1);

	const maskArr = maxScores.arraySync();
	const boxesArr = boxes.arraySync();
	const scoresArr = maxScores.arraySync();
	const classesArr = classIndices.arraySync();

	// Log score distribution
	const sortedScores = [...scoresArr].sort((a, b) => b - a);
	console.log(`  Top 10 scores: ${sortedScores.slice(0, 10).map(s => s.toFixed(4)).join(', ')}`);
	console.log(`  Total candidates: ${scoresArr.length}`);
	console.log(`  Candidates above threshold: ${scoresArr.filter(s => s >= confThreshold).length}`);

	const filteredBoxesArr = [];
	const filteredScoresArr = [];
	const filteredClassesArr = [];

	for (let i = 0; i < maskArr.length; i++) {
		if (scoresArr[i] >= confThreshold) {
			filteredBoxesArr.push(boxesArr[i]);
			filteredScoresArr.push(scoresArr[i]);
			filteredClassesArr.push(classesArr[i]);
		}
	}

	console.log(`  Filtered count: ${filteredBoxesArr.length}`);

	// Log first 5 filtered boxes
	for (let i = 0; i < Math.min(5, filteredBoxesArr.length); i++) {
		console.log(`  Filtered box[${i}]: [${filteredBoxesArr[i].map(v => v.toFixed(6)).join(', ')}], score=${filteredScoresArr[i].toFixed(4)}, class=${filteredClassesArr[i]}`);
	}

	// Return empty but correctly shaped tensors if nothing passed the threshold
	if (filteredBoxesArr.length === 0) {
		maxScores.dispose();

		console.log("  No boxes passed threshold!");
		console.log("=== END filterByConfidence DEBUG ===");
		return [
			tf.tensor2d([], [0, 4]),
			tf.tensor1d([]),
			tf.tensor1d([], 'int32')
		];
	}

	const filteredBoxes = tf.tensor2d(filteredBoxesArr, [filteredBoxesArr.length, 4]);
	const filteredScores = tf.tensor1d(filteredScoresArr);
	const filteredClasses = tf.tensor1d(filteredClassesArr, 'int32');

	console.log("=== END filterByConfidence DEBUG ===");

	maxScores.dispose();
	classIndices.dispose();

	return [filteredBoxes, filteredScores, filteredClasses];
}

/**
 * Applies Non-Maximum Suppression (NMS) to filter overlapping boxes.
 */
function applyNMS(boxes, scores, classes, iouThreshold) {
	return tf.tidy(() => {
		console.log("=== applyNMS DEBUG ===");
		console.log(`  Input boxes shape: [${boxes.shape}]`);
		console.log(`  Input scores shape: [${scores.shape}]`);
		console.log(`  IoU threshold: ${iouThreshold}`);

		if (boxes.shape[0] === 0) {
			console.log("  No boxes to process, returning empty.");
			console.log("=== END applyNMS DEBUG ===");
			return { boxes: [], scores: [], classes: [] };
		}

		const nmsIndices = tf.image.nonMaxSuppression(boxes, scores, 100, iouThreshold);
		console.log(`  NMS kept ${nmsIndices.shape[0]} boxes`);

		const finalBoxes = tf.gather(boxes, nmsIndices).arraySync();
		const finalScores = tf.gather(scores, nmsIndices).arraySync();
		const finalClasses = tf.gather(classes, nmsIndices).arraySync();

		nmsIndices.dispose();

		// Log final detections
		for (let i = 0; i < Math.min(5, finalBoxes.length); i++) {
			console.log(`  Final box[${i}]: [${finalBoxes[i].map(v => v.toFixed(6)).join(', ')}], score=${finalScores[i].toFixed(4)}, class=${finalClasses[i]}`);
		}

		console.log("=== END applyNMS DEBUG ===");
		return { boxes: finalBoxes, scores: finalScores, classes: finalClasses };
	});
}

async function handleAnnotations(boxes, scores, classes) {
    if (!boxes || boxes.length === 0) {
        show_nothing_found_animation();
        warn("Nothing found", "Annotate manually");
        return;
    }

    // Delete without triggering load_dynamic_content
    const image_filename = $("#image").attr("src").replace(/.*filename=/, "");
    if (image_filename) {
        try {
            await $.ajax({
                url: "delete_all_anno.php?image=" + encodeURIComponent(image_filename),
                type: "get"
            });
        } catch (err) {
            error("Delete Anno failed", err.statusText || err);
        }
    }

    const anno_boxes = [];
    const this_labels = get_labels();
    const img_width = $("#image").width();
    const img_height = $("#image").height();

    if (!this_labels || Object.keys(this_labels).length === 0) {
        error("ERROR", "has no labels");
        return;
    }

    for (let i = 0; i < boxes.length; i++) {
        const [xMin, yMin, xMax, yMax] = boxes[i];
        const x = Math.round(xMin * img_width);
        const y = Math.round(yMin * img_height);
        const w = Math.round((xMax - xMin) * img_width);
        const h = Math.round((yMax - yMin) * img_height);

        const this_class = classes[i];
        if (this_class === -1) continue;

        const this_label = this_labels[this_class];
        if (this_label) {
            const anno_element = get_annotate_element(this_label, x, y, w, h);
            if (anno_element) anno_boxes.push(anno_element);
        }
    }

    if (anno_boxes.length === 0) {
        show_nothing_found_animation();
        warn("Nothing found after filtering");
        return;
    }

    // Set all at once
    await anno.setAnnotations(anno_boxes);
    invalidateAnnoCache();

    // Save what we built — don't re-fetch from Annotorious
    await save_annos_batch(anno_boxes);

    // ONE UI refresh
    await load_dynamic_content();
    await create_selects_from_annotation(1);

    success("Success", "Image Detection done.");
}

var image_history = [];

function go_back() {
	if (image_history.length === 0) {
		warn("History", "Kein vorheriges Bild");
		return;
	}
	var prev_fn = image_history.pop();
	set_img_from_filename(prev_fn);
}

function show_shortcut_help() {
	var help = [
		"N = Next image",
		"B = Back (vorheriges Bild)",
		"S = Skip (ohne Annotation)",
		"O = Offtopic",
		"U = Unidentifiable",
		"K = KI-Labelling",
		"? = Diese Hilfe"
	].join("\n");
	alert(help);
}

async function skip_current_image() {
	// 1. Aktuellen Dateinamen holen
	var fn = $("#filename").html();
	if (!fn) {
		warn("Skip", "Kein Bild geladen");
		return;
	}

	// 2. Annotorious-Annotationen entfernen + DB-Annotationen löschen
	await remove_current_annos(fn);

	// 3. Nächstes Bild laden
	await load_next_random_image();
}

$(document).ready(function () {
    // Don't create a new #tensor_monitor div — it already exists in footer.php

    setInterval(function () {
        var mem = tf.memory();
        var info = "Tensors: " + mem.numTensors
            + " | DataBuffers: " + mem.numDataBuffers
            + " | Bytes: " + (mem.numBytes / 1024).toFixed(1) + " KB";

        if (mem.numBytesInGPU !== undefined && mem.numBytesInGPU > 0) {
            info += " | GPU: " + (mem.numBytesInGPU / 1024 / 1024).toFixed(2) + " MB";
        }

        if (mem.unreliable) {
            info += " | ⚠️ unreliable";
        }

        $("#tensor_monitor").html(info);
    }, 500);
});
