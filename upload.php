<?php
	include("header.php");
	include_once("functions.php");
?>

<script>
	$(document).ready(function () {
		$('#uploadForm').on('submit', function (e) {
			e.preventDefault();

			var formData = new FormData(this);

			// Loop through the selected files
			var files = $('input[type="file"]')[0].files;
			var currentIndex = 0;

			function uploadNextFile() {
				var progress = `${currentIndex} of ${files.length}`;
				if (currentIndex < files.length) {
					var file = files[currentIndex];
					formData.set('image', file);

					$.ajax({
					type: 'POST',
						url: 'upload_file.php',
						data: formData,
						cache: false,
						contentType: false,
						processData: false,
						success: function (response) {
							$('#response').html(response);
							currentIndex++;
							load_dynamic_content();
							uploadNextFile();
						}
					});
				} else {
					success("File import", `All ${files.length} file(s) imported`);
				}

				success("File import", progress);
			}

			// Start uploading files
			uploadNextFile();
		});
	});
</script>

<h1>Upload Images</h1>
<form id="uploadForm" enctype="multipart/form-data">
	<input type="file" name="image" multiple accept="image/jpeg">
	<input type="submit" value="Upload">
</form>
<div id="response"></div>

<?php
	include_once("footer.php");
?>
