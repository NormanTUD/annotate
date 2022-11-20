"use strict";

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
	tf.setBackend('wasm');
	log("set wasm");
	/*
	tf.setBackend('webgl');
	log("set webgl");
	 */

	await tf.ready();

	$("#loader").hide();
	$("#upload_button").show();
}

var anno;
var available_tags = [];
var previous = [];

function log (...msg) {
	for (var i = 0; i < msg.length; i++) {
		console.log(msg[i]);
	}
}

function refresh(){
	window.location.reload("Refresh")
}


function next_img () {
	log("reloading index.php");
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

function make_item_anno(elem, widgets={}) {
	anno = Annotorious.init({
		image: elem,
		widgets: widgets
	});
	//anno.readOnly = true;

	anno.loadAnnotations('get_current_annotations.php?first_other=1&source=' + elem.src.replace(/.*\//, ""));

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
			success: function (response) {
				success("OK", response);
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
			success: function (response) {
				success("OK", response)
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
			success: function (response) {
				success("OK", response)
			},
			error: function(jqXHR, textStatus, errorThrown) {
				error(textStatus, errorThrown);
			}
		});
	});

	anno.on('cancelSelected', function(selection) {
		log(selection);
	})
}

function create_annos () {
	var items = $(".images");
	for (var i = 0; i < items.length; i++) {
		make_item_anno(items[i]);
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

function save_anno (annotation) {
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
		success: function (response) {
			success("OK", response);
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
	var src = elem.src;
	var loc = window.location.pathname;

	var data_url = await toDataURL(src);

	var port = 12000;
	var host = window.location.host;

	var prot = window.location.protocol;
	var serve_model_url = prot + "//" + host + ":" + port + "/annotarious";

	log("loading serve_model_url" + serve_model_url);

	var r = {
		url: serve_model_url,
		type: "POST",
		dataType : 'json',
		crossDomain: true,
		processData: false,
		contentType: false,
		data: JSON.stringify({
			src: src,
			image: data_url
		}),
		success: async function (a, msg) {
			toastr["success"]("Success!", msg);
			var ki_names = get_names_from_ki_anno(a);
			if(Object.keys(ki_names).length) {
				var html = "Von der KI gefundene Objekte: ";

				var selects = [];

				log(ki_names);

				var ki_names_keys = Object.keys(ki_names);

				for (var i = 0; i < ki_names_keys.length; i++) {
					previous[i] = ki_names_keys[i];
					var this_select = "<select data-nr='" + i + "' class='ki_select_box'>";
					for (var j = 0; j < available_tags.length; j++) {
						this_select += '<option ' + ((ki_names_keys[i] == available_tags[j]) ? 'selected' : '') + ' value="' + available_tags[j] + '">' + available_tags[j] + '</option>'
					}

					this_select += "<select> (" + ki_names[ki_names_keys[i]] + ")";

					selects.push(this_select);
				}

				html += selects.join(", ");

				$("#ki_detected_names").html(html);

				$(".ki_select_box").change(function (x, y, z) {
					log("ki_select_box: ", x);
					var old_value = previous[$(this).data("nr")];
					var new_value = x.currentTarget.value

					log("from " + old_value + " to " + new_value);

					set_all_current_annotations_from_to(old_value, new_value);

					previous[$(this).data("nr")] = new_value;
				});
			} else {
				$("#ki_detected_names").html("Die KI konnte keine Objekte erkennen");
			}

			await anno.setAnnotations(a);

			var new_annos = anno.getAnnotations();
			for (var i = 0; i < new_annos.length; i++) {
				save_anno(new_annos[i]);
			}
		},
		error: function (a, msg) {
			toastr["error"]("Fehler", msg);
		}
	};

	log(r);

	var request = $.ajax(r);
}

function set_all_current_annotations_from_to (from, name) {
	var current = anno.getAnnotations();

	for (var i = 0; i < current.length; i++) {
		var old = current[i]["body"][0]["value"];
		if(from == old && old != name) {
			current[i]["body"][0]["value"] = name;
			success("changed " + old + " to " + name);
		}
	}

	anno.setAnnotations(current);

	var new_annos = anno.getAnnotations();
	for (var i = 0; i < new_annos.length; i++) {
		save_anno(new_annos[i]);
	}
}

function set_all_current_annotations_to (name) {
	var current = anno.getAnnotations();

	for (var i = 0; i < current.length; i++) {
		var old = current[i]["body"][0]["value"];
		if(old != name) {
			current[i]["body"][0]["value"] = name;
			log("changed " + old + " to " + name);
		}
	}

	anno.setAnnotations(current);

	var new_annos = anno.getAnnotations();
	for (var i = 0; i < new_annos.length; i++) {
		save_anno(new_annos[i]);
	}
}

document.onkeydown = function (e) {
	if($(":focus").length) {
		return;
	}

	e = e || window.event;
	log("detected " + e.which);
	switch (e.which) {
		case 78:
			next_img()
			break;
		case 75:
			ai_file($('#image')[0]);
			break;
		default:
			break;
	}
}
