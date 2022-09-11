<!DOCTYPE html>
<head>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@recogito/annotorious@2.7.7/dist/annotorious.min.css">
	<script src="https://cdn.jsdelivr.net/npm/@recogito/annotorious@2.7.7/dist/annotorious.min.js"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>

<body>
<?php
		include_once("functions.php");

		$files = scandir("images");

		$imgfile = "";

		$img_files = array();

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file)) {
				$img_files[] = $file;
				//$img_files[$file] = nr_of_annotations($file);
				#die(">>".nr_of_annotations($file));
				//$imgfile = $file;
			}
		}

		shuffle($img_files);

		$imgfile = $img_files[0];

		if(!$imgfile) {
			die("Cannot find an image");
		}
?>
	<a href='tutorial.mp4' target="_blank">Video-Anleitung</a>
	<div id="content">
		<p><button onClick="refresh(this)">N&auml;chstes Bild</button><br></p>
		<img id="image" src="images/<?php print $imgfile; ?>">
	</div>
	<script>
		function log (msg) {
			console.log(msg);
		}
		(function() {
			var anno = Annotorious.init({
				image: 'image' // image element or ID
			});

			anno.loadAnnotations('get_current_annotations.php?source=' + $("#image")[0].src.replace(/.*\//, ""));

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
		})()

		function refresh(){
			window.location.reload("Refresh")
		}
	</script>
</body>
