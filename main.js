"use strict";

function log (...msg) {
	for (var i = 0; i < msg.length; i++) {
		console.log(msg[i]);
	}
}

function refresh(){
	window.location.reload("Refresh")
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

function make_item_anno(elem, widgets={}) {
	log(elem);
	var anno = Annotorious.init({
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
}

function create_annos () {
	var items = $(".images");
	for (var i = 0; i < items.length; i++) {
		make_item_anno(items[i]);
	}
}

 function open_tab(evt, cityName) {
  // Declare all variables
  var i, tabcontent, tablinks;

  // Get all elements with class="tabcontent" and hide them
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }

  // Get all elements with class="tablinks" and remove the class "active"
  tablinks = document.getElementsByClassName("tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }

  // Show the current tab, and add an "active" class to the button that opened the tab
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

