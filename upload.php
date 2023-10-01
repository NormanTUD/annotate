<?php
	include("header.php");
	include_once("functions.php");
?>

<script>
	$(document).ready(function () {
		$('#uploadForm').on('submit', function (e) {
			$("#defective_files").hide();
			$("#defective_files_ul").html("");

			e.preventDefault();

			var formData = new FormData(this);

			// Loop through the selected files
			var files = $('input[type="file"]')[0].files;
			var currentIndex = 0;

			var defective_files = [];

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
							if(response.includes("Error:")) {
								defective_files.push();
								$("#defective_files_ul").append("<li>" + files[currentIndex].name + "</li>");
								$("#defective_files").show();
							}
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
<div id="response" style="height: 400px;"></div>

<div id="defective_files">
	Defective files:
	<ul id="defective_files_ul">
	</ul>
</div>

<?php
	include_once("footer.php");
?>
