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

		$img_files = shuffle_assoc($img_files);
		asort($img_files);
		$j = 0;
		$imgfile = "";
		foreach ($img_files as $f => $k) {
			if($j != 0) {
				continue;
			}
			$imgfile = $f;
			$j++;
		}

		if(!$imgfile) {
			die("Cannot find an image");
		}
		$annotation_stat = get_number_of_annotated_imgs();
?>
	<a href='tutorial.mp4' target="_blank">Video-Anleitung</a>, Anzahl annotierter Bilder: <?php print htmlentities($annotation_stat[0] ?? ""); ?>, Anzahl unannotierter Bilder: <?php print htmlentities($annotation_stat[1] ?? ""); ?>
<?php
		if($annotation_stat[1] != 0) {
			$percent = sprintf("%0.2f", ($annotation_stat[0] / $annotation_stat[1]) * 100);
			print " ($percent%)";
		}
?>
	<br>
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
