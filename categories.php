<?php
		include("header.php");
		include_once("functions.php");

		$searchtag = "";
		if(array_key_exists("searchtag", $_GET)) {
			$searchtag = $_GET["searchtag"];
		}

		$files = scandir("images");

		$img_files = array();

		foreach($files as $file) {
			if(preg_match("/\.(?:jpe?|pn)g$/i", $file)) {
				$annotations = number_of_annotations_total($file);
				if($searchtag == "" || image_has_tag($file, $searchtag)) {
					$img_files[$file] = $annotations;
				}
			}
		}

		asort($img_files);
		print_header();
?>
	<div id="content">
<?php
		$i = 0;
		foreach ($img_files as $f => $k) {
			if($k) {
				if($i > 20) {
					continue;
				}
?>
				<!--<a target="_blank" href="index.php?move_to_offtopic=<?php print $f; ?>"><img class="images" id="<?php print uniqid(); ?>" src="images/<?php print $f; ?>"></a>-->
				<img class="images" id="<?php print uniqid(); ?>" src="images/<?php print $f; ?>">
<?php
				$i++;
			}
		}
?>
	</div>
	<script>
		function make_item_anno(elem) {
			log(elem);
			var anno = Annotorious.init({
				image: elem
			});
			//anno.readOnly = true;

			anno.loadAnnotations('get_current_annotations.php?first_other=1&source=' + elem.src.replace(/.*\//, ""));

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

		create_annos();
	</script>
</body>
