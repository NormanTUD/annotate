<?php
	include("header.php");
	include_once("functions.php");

	if(get_get("delete_model")) {
		delete_model(get_get("delete_model"));
	}

	$available_models = get_list_of_models();

	if(count($available_models)) {
?>
		<h1>Current models</h1>
		
		<table border=1>
			<tr>
				<th>Name</th>
				<th>UID</th>
				<th>Delete?</th>
			</tr>
<?php
			for ($i = 0; $i < count($available_models); $i++) {
				print "<tr>\n";
				print "	<td>".$available_models[$i][0]."</td>\n";
				print "	<td>".$available_models[$i][1]."</td>\n";
				print "	<td><a href='models.php?delete_model=".$available_models[$i][1]."'>Delete!</a></td>\n";
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
	
	<em>Warning: This, depending on your server hardware, may not work. If it doesn't, do it manually on a supported system and upload the model.json and .bin files manually afterwards. Use this command to convert:</em>

	<pre>
git clone --depth 1 https://github.com/ultralytics/yolov5.git
cd yolov5
python3 -mvenv ~/.yolov5
source ~/.yolov5/bin/activate
pip3 install -r requirements.txt
python3 export.py --weights your_model.pt --img 512 512 --batch-size 1 --include tfjs
	</pre>
	<form enctype="multipart/form-data" method="POST" action="upload_pt_model.php">
		<input type="file" multiple name="pytorch_model" accept=".pt,.pb">
		<input type="text" name="model_name" placeholder="Model Name">
		<input type="submit" value="Upload and Process Model">
	</form>
<?php
	include_once("footer.php");
?>
