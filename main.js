"use strict";

const startQueryString = window.location.search;
const startUrlParams = new URLSearchParams(startQueryString);
const conf = 0.01;

var autonext_param = startUrlParams.get('autonext');
var model;
var last_load_dynamic_content = false;
var running_ki = false;
var tags = [];
var last_model_md5 = "";

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

function log (...msg) {
	for (var i = 0; i < msg.length; i++) {
		console.log(msg[i]);
	}
}

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
	$("#status_bar").html("<span style='color: black'>" + title + ": " + msg + "</span>");
}

function success (title, msg) {
	$("#status_bar").html("<span style='color: black'>" + title + ": " + msg + "</span>");
}

function warn(title, msg) {
	$("#status_bar").html("<span style='color: orange'>" + title + ": " + msg + "</span>");
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
		await ai_file($('#image')[0]);
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

	log("save anno data:", data);

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

function getUrlParam(name, defaultValue) {
	var params = new URLSearchParams(window.location.search);
	var val = parseFloat(params.get(name));
	return isNaN(val) ? defaultValue : val;
}

async function ai_file(elem) {
	if (!await checkModelAvailable()) return;

	show_ai_stuff();
	running_ki = true;
	$("body").css("cursor", "progress");
	success("Success", "KI gestartet... Bitte warten");

	await anno.clearAnnotations();
	await load_model();
	await tf.ready();

	const [modelWidth, modelHeight] = getModelInputShape();

	let res;
	try {
		res = await predict(modelWidth, modelHeight);
	} catch (e) {
		console.warn(e);
		$("body").css("cursor", "default");
		running_ki = false;
		return;
	}
	$("body").css("cursor", "default");

	var shape = getShape(res);

	console.log(`res (shape: ${shape}):`, res);

	var { boxes, scores, classes } = await processModelOutput(res);

	await handleAnnotations(boxes, scores, classes);

	running_ki = false;

	if (autonext_param) {
		await sleep(1500);
		await load_next_random_image();
	}
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
		console.info("No AI model found. Not allowing ai_file stuff");
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
		res = await model.executeAsync(image_tensor);


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

function analyzeScores(res, labelCount) {
	log(res);
	const C = res.length;
	const numPredictions = res[0][0].length;
	const numClasses = C - 4;

	if (numClasses !== labelCount) {
		warn("Label mismatch", `Expected ${labelCount} classes but model has ${numClasses}`);
	}

	let minScore = Infinity;
	let maxScore = -Infinity;
	let scoreSum = 0;
	let scoreCount = 0;

	const histo = Array(10).fill(0);  // 10 bins: 0-0.1, ..., 0.9-1.0

	for (let i = 0; i < numPredictions; i++) {
		for (let c = 0; c < numClasses; c++) {
			const score = res[0][4 + c][i];
			if (!Number.isFinite(score)) {
				error("Invalid score", `Score at pred ${i}, class ${c} is not a number: ${score}`);
				continue;
			}

			if (score < minScore) minScore = score;
			if (score > maxScore) maxScore = score;
			scoreSum += score;
			scoreCount++;

			const bin = Math.min(9, Math.floor(score * 10));
			histo[bin]++;
		}
	}

	const avgScore = scoreSum / scoreCount;

	console.log("[Score Analysis]");
	console.log(`Min: ${minScore.toFixed(6)}, Max: ${maxScore.toFixed(6)}, Avg: ${avgScore.toFixed(6)}`);
	console.log("Histogram (bins 0.0–1.0):");
	histo.forEach((count, i) => {
		const label = `${(i / 10).toFixed(1)}–${((i + 1) / 10).toFixed(1)}`;
		console.log(`${label}: ${count}`);
	});

	return { min: minScore, max: maxScore, avg: avgScore, histo };
}

function processModelOutput(res) {
	// res: Float32Array or array with shape [1, C, 8400]
	analyzeScores(res, labels.length);

	const C = res[0].length;
	const numPredictions = res[0][0].length;
	const numClasses = C - 4;

	const boxes = [];
	const scores = [];
	const classes = [];

	for (let i = 0; i < numPredictions; i++) {
		const x = res[0][0][i]; // center x
		const y = res[0][1][i]; // center y
		const w = res[0][2][i]; // width
		const h = res[0][3][i]; // height

		// Klassenscores extrahieren
		let bestScore = -Infinity;
		let bestClass = -1;
		for (let c = 0; c < numClasses; c++) {
			const score = res[0][4 + c][i];
			if (score > bestScore) {
				bestScore = score;
				bestClass = c;
			}
		}

		// Optional: Konfidenzfilter
		if (bestScore > conf) {  // Threshold nach Bedarf
			// Box-Koordinaten in relativen Werten (0..1)
			const relX = x / imgsz;
			const relY = y / imgsz;
			const relW = w / imgsz;
			const relH = h / imgsz;

			boxes.push([relX, relY, relW, relH]);
			scores.push(bestScore);
			classes.push(bestClass);
		}
	}

	return { boxes, scores, classes };
}

// verarbeitet die boxes/scores/classes und erstellt Annotationen
async function handleAnnotations(boxes, scores, classes) {
	log("handleAnnotations:", "boxes:", boxes, "scores:", scores, "classes:", classes);
	if (boxes.length === 0) {
		info("Nothing found", "Annotate manually");
		return;
	}

	const anno_boxes = [];

	for (let i = 0; i < boxes.length; i++) {
		const [x_start, y_start, x_end, y_end] = boxes[i];
		const w = Math.round((x_end - x_start) * imgsz);
		const h = Math.round((y_end - y_start) * imgsz);

		const this_class = classes[i];
		const this_score = scores[i];

		if (this_class === -1) continue;

		if (Object.keys(labels).length === 0) {
			error("ERROR", "has no labels");
			return;
		}

		const this_label = labels[this_class];

		log(`this_label: ${this_label}, this_class: ${this_class}`);

		if(this_label) {
			var anno_element = get_annotate_element(this_label, x_start, y_start, w, h);
			if(anno_element) {
				anno_boxes.push(anno_element);
			}
		} else {
			error("ERROR", `this_label was empty: ${this_label}`);
		}
	}

	success("Success", "Image Detection ran successfully");
	log("anno_boxes", anno_boxes);

	await anno.setAnnotations(anno_boxes);

	const new_annos = await anno.getAnnotations();
	for (const ann of new_annos) {
		await save_anno(ann);
	}

	success("Success", "Image Detection done.");
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

async function create_selects_from_annotation(force=0) {
	if($(":focus").is("select") && !force) {
		return;
	}
	if(typeof(anno) != "object") {
		return;
	}
	var ki_names = get_names_from_ki_anno(await anno.getAnnotations());

	if(Object.keys(ki_names).length) {
		var html = "";

		var selects = [];

		var ki_names_keys = Object.keys(ki_names);

		for (var i = 0; i < ki_names_keys.length; i++) {
			previous[i] = ki_names_keys[i];
			var this_select = "<select data-nr='" + i + "' class='ki_select_box'>";
			for (var j = 0; j < tags.length; j++) {
				this_select += '<option ' + ((ki_names_keys[i] == tags[j]) ? 'selected' : '') + ' value="' + tags[j] + '">' + tags[j] + '</option>'
			}

			this_select += "<select> (" + ki_names[ki_names_keys[i]] + ")";

			selects.push(this_select);
		}

		html += selects.join(", ");

		if($("#ki_detected_names").html() != html) {
			$("#ki_detected_names").html(html);
		}

		$(".ki_select_box").change(async function (x, y, z) {
			var old_value = previous[$(this).data("nr")];
			var new_value = x.currentTarget.value

			await set_all_current_annotations_from_to(old_value, new_value);
			await create_selects_from_annotation(1);

			previous[$(this).data("nr")] = new_value;
		});
	} else {
		if(!running_ki) {
			if($("#ki_detected_names").html() != "") {
				$("#ki_detected_names").html("");
			}
		} else {
			var msg = "Please wait, Image Detection is running...";

			if($("#ki_detected_names").html() != msg) {
				$("#ki_detected_names").html(msg);
			}
		}
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
			widget: 'TAG', vocabulary: tags
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

async function load_next_random_image (fn=false) {
	if(fn) {
		set_img_from_filename(fn);
	} else {
		var ajax_url = "get_random_unannotated_image.php";
		var queryString = window.location.search;
		var urlParams = new URLSearchParams(queryString);
		var like = urlParams.get('like');

		if(like) {
			ajax_url += "?like=" + encodeURI(like);
		}

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
			if(Object.keys(e).includes("message")) {
				e = e.message;
			}

			error("" + e);
		}
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
			ai_file($('#image')[0]);
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
		console.warn('Error:', error.message);
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
