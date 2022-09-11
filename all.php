<!DOCTYPE html>
<head>
	<title>Bildannotationstool</title>
	<link rel="stylesheet" href="annotorious.min.css">
	<script src="annotorious.min.js"></script>
	<script src="jquery.min.js"></script>
</head>

<body>
<?php
		include_once("functions.php");

		$files = scandir("images");

		$img_files = array();

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file)) {
				$annotations = number_of_annotations($user_id, $file);
				$img_files[$file] = $annotations;
			}
		}

		asort($img_files);
		print_header();
?>
	<div id="content">
<?php
		foreach ($img_files as $f => $k) {
?>
			<img class="images" id="<?php print uniqid(); ?>" src="images/<?php print $f; ?>">
<?php
		}
?>
	</div>
	<script>
/*
		function log (msg) {
			console.log(msg);
		}
		function make_item_anno(elem) {
			log(elem);
			var anno = Annotorious.init({
				image: elem
			});

			anno.loadAnnotations('get_current_annotations.php?source=' + elem.src.replace(/.*\//, ""));

			// Add event handlers using .on  
			anno.on('createAnnotation', function(annotation) {
				// Do something
				log(annotation);
				var a = {
					"position": annotation.target.selector.value,
					"body": annotation.body,
					"id": annotation.id,
					"source": annotation.target.source.replace(/.*\//, ""),
					"full": JSON.stringify(annotation)
				};
				log(a);
				$.ajax({
					url: "submit.php",
					type: "post",
					data: a,
					success: function (response) {
						log(response)
					},
					error: function(jqXHR, textStatus, errorThrown) {
						console.log(textStatus, errorThrown);
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
				log(a);
				$.ajax({
					url: "submit.php",
					type: "post",
					data: a,
					success: function (response) {
						log(response)
					},
					error: function(jqXHR, textStatus, errorThrown) {
						console.log(textStatus, errorThrown);
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
						log(response)
					},
					error: function(jqXHR, textStatus, errorThrown) {
						console.log(textStatus, errorThrown);
					}
				});
			});
		}

		function refresh(){
			window.location.reload("Refresh")
		}

		var items = $(".images");
		for (var i = 0; i < items.length; i++) {
			make_item_anno(items[i]);
		}
 */
	</script>
</body>
