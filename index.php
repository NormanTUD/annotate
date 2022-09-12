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

		if(array_key_exists("move_to_offtopic", $_GET)) {
			if(!preg_match("/\.\./", $_GET["move_to_offtopic"]) && preg_match("/\.jpg/", $_GET["move_to_offtopic"])) {
				rename("images/".$_GET["move_to_offtopic"], "offtopic/".$_GET["move_to_offtopic"]);
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

		if(array_key_exists("edit", $_GET)) {
			$imgfile = $_GET["edit"];
		}

		if(!$imgfile) {
			die("Cannot find an image");
		}
		print_header();
?>
	<br>
	<table>
		<tr>
			<td>
				<div id="content" style="padding: 30px;">
					<p><button onClick="refresh(this)">N&auml;chstes Bild</button><br></p>
					<!-- <p><button><a href="index.php?move_to_offtopic=<?php print $imgfile; ?>">Bild ist Off Topic</a></button><br></p> -->
					<img id="image" src="images/<?php print $imgfile; ?>">
					<br><?php print $imgfile; ?>
				</div>
			</td>
			<td>
				Aktuelle Tags (anklicken für Beispieldaten):
<?php
				$tags = get_current_tags();
				$tags_as_array = [];
				print "<ul>";
				foreach ($tags as $tag => $nr) {
					print "<li><a target='_blank' href='categories.php?searchtag=".htmlentities(urlencode($tag))."'>$tag</li>";
					$tags[] = $tag;
				}
				print "</ul>";


?>
			</td>
		</tr>
	</table>

	<script>
		function log (msg) {
			console.log(msg);
		}
		(function() {
			var anno = Annotorious.init({
				image: 'image',
					widgets: [
						'COMMENT',
						{ widget: 'TAG', vocabulary: [ <?php print '"'.join('", "', $tags).'"'; ?> ] }
					]
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
			//anno.readOnly = true;
		})()

		function write_to_current_inputfield (msg) {
			$($($(".r6o-autocomplete").children()[0]).children()[0]).val(msg + "\n").trigger("change");
		}

		function refresh(){
			window.location.reload("Refresh")
		}
	</script>
</body>
