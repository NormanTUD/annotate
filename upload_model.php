<?php
	include("header.php");
	include_once("functions.php");
?>
	<h1>Upload yolov5-PyTorch Model</h1>
	<p>Upload a .pt-file of your model. Name it, and you can then use it to annotate files.</p>
	<form id="uploadForm" enctype="multipart/form-data" method="POST" action="upload_pt_model.php">
		<input type="file" multiple name="pytorch_model" accept=".pt,.pb">
		<input type="text" name="model_name" placeholder="Model Name">
		<input type="submit" value="Upload and Process Model">
	</form>
<?php
	include_once("footer.php");
?>
