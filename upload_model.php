<?php
	include("header.php");
	include_once("functions.php");
?>
	<h1>Upload and Process PyTorch Model</h1>
	<form id="uploadForm" enctype="multipart/form-data" method="POST" action="upload_pt_model.php">
		<input type="file" name="pytorch_model" accept=".pt">
		<input type="text" name="model_name" placeholder="Model Name">
		<input type="submit" value="Upload and Process Model">
	</form>
<?php
	include_once("footer.php");
?>
