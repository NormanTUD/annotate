"use strict";

var zoom_input;
var zoom_factor = 1.0;
var used_model = null;

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

	$("#status_bar_msg")
		.html("<span style='color:" + color + "'>" + text + "</span>")
		.parent().css("display", "block");
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
			"full": JSON.stringify(annotation),
			"used_model": used_model
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

async function processModelOutput(res, modelWidth, modelHeight) {
    console.group("🔥 YOLO Post-Processing DEBUG: Finale Bereinigung");
    const startTime = performance.now();

    const confThreshold = conf || 0.3;
    const iouThreshold = 0.45;

    // *** 1. Rohdaten-Extraktion und Shape-Ermittlung ***
    let rawData;

    if (Array.isArray(res) && Array.isArray(res[0]) && res[0].length < 200) {
        if (Array.isArray(res[0][0])) { rawData = res[0].flat(); } else { rawData = res[0]; }
    } else if (Array.isArray(res) && res.length > 20000) { rawData = res; }
    else { console.error(`❌ Strukturfehler: Die Modellausgabe ('res') ist nicht das erwartete Array.`); console.groupEnd(); return { boxes: [], scores: [], classes: [] }; }

    const TOTAL_ELEMENTS = rawData.length;

    const FEATURE_TO_CANDIDATES = [
        { F: 84, C: 8400, classes: 80, total: 705600 },
        { F: 61, C: 13125, classes: 57, total: 800625 },
        { F: 25, C: 32025, classes: 21, total: 800625 }
    ];

    let FINAL_FEATURES = 0;
    let KNOWN_CLASSES_COUNT = 0;
    let FINAL_CANDIDATES = 0;
    let DYNAMIC_MATCH = false;

    for (const set of FEATURE_TO_CANDIDATES) {
        if (TOTAL_ELEMENTS === set.total) {
            FINAL_FEATURES = set.F;
            KNOWN_CLASSES_COUNT = set.classes;
            FINAL_CANDIDATES = set.C;
            DYNAMIC_MATCH = true;
            break;
        }
    }

    if (!DYNAMIC_MATCH) {
        console.error(`❌ Kritischer Shape-Fehler: Gesamtanzahl ${TOTAL_ELEMENTS} passt zu keinem bekannten Modell-Output.`);
        console.groupEnd();
        return { boxes: [], scores: [], classes: [] };
    }

    console.log(`ℹ️ Modell-Input: ${modelWidth}x${modelHeight}, Klassen: ${KNOWN_CLASSES_COUNT}`);
    console.log(`✅ Shape: [Features: ${FINAL_FEATURES}, Kandidaten: ${FINAL_CANDIDATES}] (Total: ${TOTAL_ELEMENTS})`);

    // *************************************************************************************
    // 2. TENSOR-ERSTELLUNG UND TRANSPOSE
    // *************************************************************************************

    let predictionsTensor = tf.tensor(rawData, [FINAL_FEATURES, FINAL_CANDIDATES]).expandDims(0);
    predictionsTensor = predictionsTensor.transpose([0, 2, 1]);
    console.log(`1. Tensor transponiert. Shape: [${predictionsTensor.shape.join(', ')}]`);

    // *************************************************************************************
    // 3. KORRIGIERTE AUFTEILUNG: [4 Box, C Klassen]
    // *************************************************************************************

    const [rawBoxes, scores] = tf.split(predictionsTensor, [4, KNOWN_CLASSES_COUNT], 2);
    const boxes = rawBoxes.squeeze();
    const scoresSqueezed = scores.squeeze();
    console.log(`2. Aufteilung: Boxen[${boxes.shape.join(', ')}], Scores[${scoresSqueezed.shape.join(', ')}]`);

    // 4. Maximum Score und Klasse finden (Für die gesamte Rohliste)
    const maxScores = scoresSqueezed.max(1);
    const classes = scoresSqueezed.argMax(1);

    // *************************************************************************************
    // 5. FILTERN NACH KONFIDENZ & NMS
    // *************************************************************************************

    const maxScoresArray = await maxScores.array();

    let initialHighConfidenceCount = 0;
    const keepIndices = [];
    maxScoresArray.forEach((score, index) => {
        if (score >= confThreshold) {
            keepIndices.push(index);
            initialHighConfidenceCount++;
        }
    });

    console.log(`5. Konfidenz-Filterung: ${initialHighConfidenceCount} Boxen >= ${confThreshold} behalten.`);

    if (initialHighConfidenceCount === 0) {
        console.warn("⚠️ KEINE Boxen haben den Konfidenz-Schwellenwert erreicht.");
        tf.dispose([predictionsTensor, rawBoxes, scores, boxes, scoresSqueezed, maxScores, classes]);
        console.groupEnd();
        return { boxes: [], scores: [], classes: [] };
    }

    // Die Klassen-IDs werden zusammen mit Boxen und Scores gefiltert
    const filteredBoxes = tf.gather(boxes, keepIndices);
    const filteredScores = tf.gather(maxScores, keepIndices);
    const filteredClasses = tf.gather(classes, keepIndices); // KORREKTUR: Synchronisation

    // NMS Vorbereitung und Ausführung
    const [cx, cy, w, h] = tf.split(filteredBoxes, 4, 1);
    const x_min = tf.sub(cx, tf.div(w, 2));
    const y_min = tf.sub(cy, tf.div(h, 2));
    const x_max = tf.add(cx, tf.div(w, 2));
    const y_max = tf.add(cy, tf.div(h, 2));
    const boxesForNMS = tf.concat([y_min, x_min, y_max, x_max], 1);

    const nmsIndices = await tf.image.nonMaxSuppressionAsync(
        boxesForNMS,
        filteredScores,
        filteredBoxes.shape[0],
        iouThreshold,
        confThreshold
    );

    const finalIndices = await nmsIndices.array();

    console.log(`6. NMS-Ergebnis: ${finalIndices.length} finale Boxen behalten.`);

    // *************************************************************************************
    // 7. FOKUSSIERTER DEBUG FÜR FINALE BOX
    // *************************************************************************************
    if (finalIndices.length > 0) {
        const winningIndex = keepIndices[finalIndices[0]];

        const winningScoresTensor = scoresSqueezed.slice([winningIndex, 0], [1, KNOWN_CLASSES_COUNT]);
        const winningScoresArray = await winningScoresTensor.array();

        const allScores = winningScoresArray[0];

        let trueMaxScore = 0;
        let trueMaxIndex = -1;
        allScores.forEach((score, i) => {
            if (score > trueMaxScore) {
                trueMaxScore = score;
                trueMaxIndex = i;
            }
        });

        console.log("DEBUG FOKUSSIERT: Scores der finalen Box:");
        console.log(`- Score bei ID 2 (Car/Aktuell): ${allScores[2].toFixed(4)}`);
        console.log(`- Score bei ID 15 (Cat/Soll): ${allScores[15].toFixed(4)}`);
        console.log(`- Tatsächlicher Max Score gefunden bei ID ${trueMaxIndex}: ${trueMaxScore.toFixed(4)}`);
    }

    // *************************************************************************************
    // 8. DYNAMISCHE NORMALISIERUNG AUF 0..1
    // *************************************************************************************

    const scaleFactorX = 1 / modelWidth;
    const scaleFactorY = 1 / modelHeight;

    const finalFilteredBoxes = tf.gather(filteredBoxes, finalIndices);

    const [finalCx, finalCy, finalW, finalH] = tf.split(finalFilteredBoxes, 4, 1);

    const finalCxNormalized = finalCx.mul(scaleFactorX);
    const finalWNormalized = finalW.mul(scaleFactorX);
    const finalCyNormalized = finalCy.mul(scaleFactorY);
    const finalHNormalized = finalH.mul(scaleFactorY);

    const finalBoxesTensor = tf.concat([finalCxNormalized, finalCyNormalized, finalWNormalized, finalHNormalized], 1);

    const finalScoresTensor = tf.gather(filteredScores, finalIndices);
    const finalClassesTensor = tf.gather(filteredClasses, finalIndices); // KORREKTUR: Zugriff auf gefilterte Klassen

    const finalBoxes = await finalBoxesTensor.array();
    const keepScores = await finalScoresTensor.array();
    const keepClasses = await finalClassesTensor.array();

    // *************************************************************************************
    // 9. ZUSÄTZLICHER DEBUG FÜR FINALE KLASSEN
    // *************************************************************************************

    if (keepClasses.length > 0) {
        console.log(`DEBUG FINALE KLASSEN (NEU): Max Score: ${keepScores[0].toFixed(4)}, Klasse ID: ${keepClasses[0]} (Sollte 15 sein)`);
    } else {
        console.log(`DEBUG FINALE KLASSEN: Keine Boxen nach NMS.`);
    }

    // *************************************************************************************
    // 10. RESSOURCEN FREIGEBEN (Cleanup)
    // *************************************************************************************
    const tensorsToDispose = [
        predictionsTensor, rawBoxes, scores, boxes, scoresSqueezed, maxScores, classes,
        filteredBoxes, filteredScores, filteredClasses, // NEU: sampleScores entfernt
        cx, cy, w, h, x_min, y_min, x_max, y_max, boxesForNMS, nmsIndices,
        finalFilteredBoxes, finalCx, finalCy, finalW, finalH,
        finalCxNormalized, finalCyNormalized, finalWNormalized, finalHNormalized,
        finalBoxesTensor, finalScoresTensor, finalClassesTensor
    ];
    tf.dispose(tensorsToDispose);
    console.log(`10. ${tensorsToDispose.length} Tensoren freigegeben.`);

    const endTime = performance.now();
    console.log(`✅ Post-Processing abgeschlossen in ${(endTime - startTime).toFixed(2)} ms.`);
    console.log(`Rückgabe: ${finalBoxes.length} Boxen.`);
    console.groupEnd();

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

function clamp(v, a, b) {
	if (isNaN(v)) return a;
	if (v < a) return a;
	if (v > b) return b;
	return v;
}

async function handleAnnotations(boxes, scores, classes) {
	if(enable_debug) {
		log("handleAnnotations:", "boxes:", boxes, "scores:", scores, "classes:", classes);
	}

	if (boxes.length === 0) {
		show_nothing_found_animation();
		warn("Nothing found", "Annotate manually");
		return;
	}

	delete_all_anno_current_image();

	const anno_boxes = [];
	const this_labels = get_labels();

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

		if (Object.keys(this_labels).length === 0) {
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

	watch_svg_auto();

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

				if (!found) {
					this_select += `<option selected value="${ki_names_keys[i]}">${ki_names_keys[i]}</option>`;
				}

				this_select += `</select> (${ki_names[ki_names_keys[i]]})`;
				selects.push(this_select);
			}

			html += selects.join(", ");

			var box = $("#ki_detected_names");

			if (box.html() !== html) {
				box.stop(true, true);

				if (!$.trim(box.html())) {
					box.css({ width: 0 });
					box.html(html);
					box.animate({ width: "100%" }, 10);
				} else {
					box.html(html);
				}
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

	create_zoom_slider();

	create_rotation_slider();

	await load_dynamic_content();

	await make_item_anno($("#image")[0], [
		{
			widget: 'TAG',
			vocabulary: tags
		}
	]);
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

async function set_img_from_filename(fn, no_reset_zoom = false, reload = false) {
	if(!no_reset_zoom) {
		reset_zoom();
	}

	set_image_url(fn);

	if (!fn) {
		$("#annotation_area").hide();
		$("#no_imgs_left").show();
		watch_svg_auto();
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

	watch_svg_auto();
}

async function load_next_random_image(fn = false) {
	if (fn) {
		await set_img_from_filename(fn);
	} else {
		let ajax_url = "get_random_unannotated_image.php";
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
	load_next_random_image()
}

setInterval(create_selects_from_annotation, 1000);

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

	watch_svg_auto();
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

	watch_svg_auto();
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
	rotation_input.value = 0;
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

	/* -----------------------------
	   LOAD INITIAL ROTATION
	------------------------------ */

	try {
		const r = await fetch(`get_image_rotation.php?filename=${encodeURIComponent(fn)}`);
		const j = await r.json();
		if (j.ok) {
			rotation_input.value = j.rotation;
			val.textContent = j.rotation + "°";
		}
	} catch (e) {
		console.warn("Rotation load failed", e);
	}

	/* -----------------------------
	   LIVE UPDATE + AUTO SAVE
	------------------------------ */

	let save_timeout = null;

	rotation_input.addEventListener('change', (ev) => {
		const rot = parseInt(ev.target.value, 10);
		val.textContent = rot + "°";

		if (save_timeout) clearTimeout(save_timeout);

		save_timeout = setTimeout(async () => {
			try {
				await fetch(
					`save_image_rotation.php?filename=${encodeURIComponent(fn)}&rotation=${rot}`
				);
				await set_img_from_filename(fn, true, true);
			} catch (e) {
				console.warn("Rotation save failed", e);
			}
		}, 150);
	});

	resetBtn.onclick = async function () {
		rotation_input.value = 0;
		val.textContent = "0°";
		try {
			await fetch(
				`save_image_rotation.php?filename=${encodeURIComponent(fn)}&rotation=0`
			);
			await set_img_from_filename(fn, true, true);
		} catch (e) {
			console.warn("Rotation reset failed", e);
		}
	};
}
