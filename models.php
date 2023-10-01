<?php
	include("header.php");
	include_once("functions.php");

	$available_models = get_list_of_models();

	if(count($available_models)) {
?>
		<h1>Aktuelle Modelle</h1>
		
		<table border=1>
			<tr>
				<th>Name</th>
				<th>UID</th>
				<th>Löschen?</th>
			</tr>
<?php
			for ($i = 0; $i < count($available_models); $i++) {
				print "<tr>\n";
				print "	<td>".$available_models[$i][0]."</td>\n";
				print "	<td>".$available_models[$i][1]."</td>\n";
				print "	<td>DELETE</td>\n";
				print "</tr>\n";
			}
?>

		</table>
<?php
	}
?>

	<h1>Upload tfjs Model</h1>
	<p>Upload a model.json and the according .bin files of your model. Name it, and you can then use it to annotate files.</p>
	<form enctype="multipart/form-data" method="POST" action="upload_tfjs_model.php">
		<input type="file" multiple name="tfjs_model[]" accept=".json,.bin">
		<input type="text" name="model_name" placeholder="Model Name">
		<input type="submit" value="Upload and Process Model">
	</form>

	<h1>Upload yolov5-PyTorch Model</h1>
	<p>Upload a .pt-file of your model. Name it, and you can then use it to annotate files.</p>
	<form enctype="multipart/form-data" method="POST" action="upload_pt_model.php">
		<input type="file" multiple name="pytorch_model" accept=".pt,.pb">
		<input type="text" name="model_name" placeholder="Model Name">
		<input type="submit" value="Upload and Process Model">
	</form>
<?php
	include_once("footer.php");
?>
