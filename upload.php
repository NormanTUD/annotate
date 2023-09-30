<?php
	include("header.php");
	include_once("functions.php");
?>
	<script>
		$(document).ready(function () {
			$('#uploadForm').on('submit', function (e) {
				e.preventDefault();

				var formData = new FormData(this);

				$.ajax({
					type: 'POST',
					url: 'upload_files.php',
					data: formData,
					cache: false,
					contentType: false,
					processData: false,
					success: function (response) {
						$('#response').html(response);
					}
				});
			});
		});
	</script>

	<h1>Upload Images</h1>
	<form id="uploadForm" enctype="multipart/form-data">
		<input type="file" name="images[]" multiple accept="image/*">
		<input type="submit" value="Upload">
	</form>
	<div id="response"></div>
<?php
	include_once("footer.php");
?>
