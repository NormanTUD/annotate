"use strict";

var model;

var tags = [];

function uuidv4() {
	return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
		(c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
	);
}

async function load_model () {
	if(model) {
		return;
	}

	model = await tf.loadGraphModel(
		'./model.json',
		{
			onProgress: function (p) {
				var percent = p * 100;
				percent = percent.toFixed(0);
				$("#loader").html("Loading Model, " + percent + "%<br>\n");
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

function move_to_unidentifiable() {
	var file = $("#image")[0].src.replace(/.*\//, "");
	window.location.href = "index.php?move_to_unidentifiable=" + file;
}

function move_to_offtopic() {
	var file = $("#image")[0].src.replace(/.*\//, "");
	window.location.href = "index.php?move_to_offtopic=" + file;
}

function next_img () {
	window.location.href = "index.php";
}

function success (title, msg) {
	toastr.options = {
		"closeButton": false,
		"debug": false,
		"newestOnTop": true,
		"progressBar": true,
		"positionClass": "toast-bottom-center",
		"preventDuplicates": true,
		"onclick": null,
		"showDuration": "300",
		"hideDuration": "1000",
		"timeOut": "5000",
		"extendedTimeOut": "1000",
		"showEasing": "swing",
		"hideEasing": "linear",
		"showMethod": "fadeIn",
		"hideMethod": "fadeOut"
	};

	toastr["success"](title, msg);
}



function error (title, msg) {
	toastr.options = {
		"closeButton": false,
		"debug": false,
		"newestOnTop": true,
		"progressBar": true,
		"positionClass": "toast-bottom-center",
		"preventDuplicates": true,
		"onclick": null,
		"showDuration": "300",
		"hideDuration": "1000",
		"timeOut": "5000",
		"extendedTimeOut": "10000",
		"showEasing": "swing",
		"hideEasing": "linear",
		"showMethod": "fadeIn",
		"hideMethod": "fadeOut"
	};

	toastr["error"](title, msg);
}

async function make_item_anno(elem, widgets={}) {
	if(typeof(anno) == "object") {
		await anno.destroy();
		anno = undefined;
	}

	anno = await Annotorious.init({
		image: elem,
		widgets: widgets
	});

	await anno.loadAnnotations('get_current_annotations.php?first_other=1&source=' + elem.src.replace(/.*\//, ""));
	await create_selects_from_annotation();

	// Add event handlers using .on  
	anno.on('createAnnotation', function(annotation) {
		// Do something
		var a = {
			"position": annotation.target.selector.value,
			"body": annotation.body,
			"id": annotation.id,
			"source": annotation.target.source.replace(/.*\//, ""),
			"full": JSON.stringify(annotation)
		};

		$.ajax({
			url: "submit.php",
			type: "post",
			data: a,
			success: async function (response) {
				success("OK", response);
				await load_dynamic_content();
				await create_selects_from_annotation();
			},
			error: function(jqXHR, textStatus, errorThrown) {
				error(textStatus, errorThrown);
			}
		});
	});

	anno.on('updateAnnotation', function(annotation) {
		var a = {
			"position": annotation.target.selector.value,
			"body": annotation.body,
			"id": annotation.id,
			"source": annotation.target.source.replace(/.*\//, ""),
			"full": JSON.stringify(annotation)
		};
		$.ajax({
			url: "submit.php",
			type: "post",
			data: a,
			success: async function (response) {
				success("OK", response)
				await load_dynamic_content();
				await create_selects_from_annotation();
			},
			error: function(jqXHR, textStatus, errorThrown) {
				error(textStatus, errorThrown);
			}
		});
	});


	anno.on('deleteAnnotation', function(annotation) {
		var a = {
			"position": annotation.target.selector.value,
			"body": annotation.body,
			"id": annotation.id,
			"source": annotation.target.source.replace(/.*\//, ""),
			"full": JSON.stringify(annotation)
		};
		log(a);
		$.ajax({
			url: "delete_annotation.php",
			type: "post",
			data: a,
			success: async function (response) {
				success("OK", response)
				await create_selects_from_annotation();
			},
			error: function(jqXHR, textStatus, errorThrown) {
				error(textStatus, errorThrown);
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
};

const toDataURL = url => fetch(url)
	.then(response => response.blob())
	.then(blob => new Promise((resolve, reject) => {
		const reader = new FileReader()
		reader.onloadend = () => resolve(reader.result.split(',')[1])
		reader.onerror = reject
		reader.readAsDataURL(blob)
	}));

async function save_anno (annotation) {
	// Do something
	var a = {
		"position": annotation.target.selector.value,
		"body": annotation.body,
		"id": annotation.id,
		"source": annotation.target.source.replace(/.*\//, ""),
		"full": JSON.stringify(annotation)
	};

	$.ajax({
		url: "submit.php",
		type: "post",
		data: a,
		success: async function (response) {
			success("OK", response);
			await load_dynamic_content();
		},
		error: function(jqXHR, textStatus, errorThrown) {
			error(textStatus, errorThrown);
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

async function ai_file (elem) {
	$("body").css("cursor", "progress");
	toastr["success"]("Success!", "KI gestartet... Bitte warten");
	await anno.clearAnnotations();

	await load_model();
	await tf.ready();

	var [modelWidth, modelHeight] = model.inputs[0].shape.slice(1, 3);

	var res = await model.executeAsync(tf.browser.fromPixels($("#image")[0]).resizeBilinear([modelWidth,modelHeight]).div(255).expandDims());
	$("body").css("cursor", "default");

	const [boxes, scores, classes] = res.slice(0, 3);

	const boxes_data = await boxes.arraySync()[0];
	const scores_data = await scores.arraySync()[0];
	const classes_data = await classes.arraySync()[0];

	var a = [];

	for (var i = 0; i < boxes_data.length; i++) {
		var this_box = boxes_data[i];
		var this_score = scores_data[i];
		var this_class = classes_data[i];

		if(this_class != -1) {
			var this_label = labels[this_class];

			var name = this_label + " (" + (this_score * 100).toFixed(0) + "%)";

			var img_width = $("#image")[0].width;
			var img_height = $("#image")[0].height;
			
			var x_start = parseInt(this_box[0] * img_width);
			var y_start = parseInt(this_box[1] * img_height);

			var x_end = Math.abs(x_start - parseInt(this_box[2] * img_width));
			var y_end = Math.abs(y_start - parseInt(this_box[3] * img_height));

			var w = x_end;
			var h = y_end;

			var this_elem = {
				"type": "Annotation", 
				"body": [ 
					{
						"type": "TextualBody", 
							"value": this_label,
							"purpose": "tagging"
					} 
				], 
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
			};

			a.push(this_elem);
		}
	}

	log(a);

	var msg = "KI erfolgreich durchgelaufen";

	toastr["success"]("Success!", msg);
	log("AAAA", a);
	await anno.setAnnotations(a);


	var new_annos = await anno.getAnnotations();
	for (var i = 0; i < new_annos.length; i++) {
		await save_anno(new_annos[i]);
	}

	await create_selects_from_annotation();
}

async function create_selects_from_annotation() {
	if(typeof(anno) != "object") {
		log("X");
		return;
	}
	log("Y");
	var ki_names = get_names_from_ki_anno(await anno.getAnnotations());

	if(Object.keys(ki_names).length) {
		var html = "";

		var selects = [];

		log(ki_names);

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

		$("#ki_detected_names").html(html);

		$(".ki_select_box").change(async function (x, y, z) {
			log("ki_select_box: ", x);
			var old_value = previous[$(this).data("nr")];
			var new_value = x.currentTarget.value

			log("from " + old_value + " to " + new_value);

			await set_all_current_annotations_from_to(old_value, new_value);

			previous[$(this).data("nr")] = new_value;
		});
	} else {
		$("#ki_detected_names").html("Keine Objekte markiert");
	}
}

async function set_all_current_annotations_from_to (from, name) {
	log("set_all_current_annotations_from_to");
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
	await create_selects_from_annotation();
}

async function load_page() {
	await load_dynamic_content();

	await make_item_anno($("#image")[0], [
		{
			widget: 'TAG', vocabulary: tags
		}
	]);

}

async function load_dynamic_content () {
	await $.ajax({
		url: "print_home.php",
		type: "GET",
		dataType: "html",
		success: function (data) {
			$('#tab_home').html("");
			$('#tab_home').html(data);
		},
		error: function (xhr, status) {
			error("Error loading the List", "Sorry, there was a problem!");
		}
	});

	await $.ajax({
		url: "get_current_list.php",
		type: "GET",
		dataType: "html",
		success: function (data) {
			var d = JSON.parse(data);
			tags = Object.keys(d.tags);
			$('#list').html("");
			$('#list').html(d.html);
		},
		error: function (xhr, status) {
			error("Error loading the List", "Sorry, there was a problem!");
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

	$("#ki_detected_names").html("");
	$("#filename").html(fn);
	$("#image").prop("src", "images/" + fn);

	await load_page();
}

async function load_next_random_image (fn=false) {
	if(fn) {
		set_img_from_filename(fn);
	} else {
		await $.ajax({
			url: "get_random_unannotated_image.php",
			type: "GET",
			dataType: "html",
			success: async function (fn) {
				await set_img_from_filename(fn);
			},
			error: function (xhr, status) {
				error("Error loading the List", "Sorry, there was a problem!");
			}
		});
	}

}

function add_function_debugger () {
        for (var i in window) {
                if(typeof(window[i]) == "function" && !["log", "$", "getComputedStyle", "add_function_debugger", "Or", "N", "wS", "Wc", "Wx", "Or", "Xc", "dispatchEvent", "removeEventListener", "addEventListener", "clearImmediate", "setImmediate", "jQuery", "xi", "h", "$e", "Oi", "Pr", "TS", "DS", "Kc", "queueMicrotask", "createImageBitmap", "clearTimeout", "structuredClone", "requestIdleCallback", "setResizable", "getDefaultComputedStyle", "close", "stop", "focus", "blur", "alert", "open", "prompt", "print", "captureEvents", "moveTo", "getSelection", "matchMedia", "releaseEvents", "confirm", "resizeTo", "resizeBy", "scrollBy", "scroll", "scrollTo", "sizeToContent", "updateCommands", "find", "cancelIdleCallback", "requestAnimationFrame", "cancelAnimationFrame", "reportError", "btoa", "atob", "setTimeout", "setInterval", "clearInterval", "fetch", "PS", "scrollByPages", "postMessage", "moveBy", "dump", "scrollByLines", "scrollByPages"].includes(i)) {

                        // wenn der Name der Funktion nicht den String "original_function enthält"
                        if(i.indexOf("original_function") == -1) {

                                // kopiere die Funktion namens "i" nach "i_original_function"
                                window[i + "_original_function"] = window[i];

                                try {
					log("Replacing " + i);
                                        var execute_this = `
                                        window["${i}"] = function (...args) {
                                                var _start_time = + new Date();

						var old_annotations = 0;
						var new_annotations = 0;

						if(typeof(anno) == "object") {
							old_annotations = anno.getAnnotations().length;
						}

                                                var result = window["${i}_original_function"](...args);

						if(typeof(anno) == "object") {
							new_annotations = anno.getAnnotations().length;
						}

                                                var _end_time = + new Date();

						if(typeof(anno) == "object" && old_annotations != new_annotations) {
							log("========== function ${i} ==========: " + new_annotations);
							console.trace();
						}
                                                return result;
                                        }
                                        `;

                                        eval(execute_this);

                                } catch (e) {
                                        console.warn(e);
                                        log(i);
                                        window[i] = window[i + "_original_function"];
                                }
                        }
                }
        }
}

document.onkeydown = function (e) {
	if($(":focus").length) {
		return;
	}

	e = e || window.event;
	//log(e.which);
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
