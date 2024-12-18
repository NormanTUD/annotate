<?php
header("Access-Control-Allow-Origin: *");
include_once("functions.php");
?>
<!DOCTYPE html>
<head>
	<title>annotate</title>
	<link rel="stylesheet" href="libs/annotorious.min.css">
	<link rel="stylesheet" href="style.css">
	<script src="libs/plotly-latest.min.js"></script>
	<script src="libs/annotorious.min.js"></script>
	<script src="libs/jquery.min.js"></script>
	<script src="main.js"></script>
	<script src='libs/tf.js'></script>
	<script src='libs/tf-backend-wasm.min.js'></script>
	<script>
		var labels = <?php include("labels.php"); ?>;
	</script>
</head>

<body>

<!-- Tab content -->
<div id="top">
	<span id="tab_home_top"><?php include("print_home.php"); ?></span><span id="memory_debugger"></span>
</div>

<?php
	if(isset($_GET["searchtag"])) {
		print "<br><a href='export_annotations.php?show_categories[]=".urlencode($_GET["searchtag"])."'>Diese Kategorie exportieren</a>";
		print "<br><a href='export_annotations.php?format=html&show_categories[]=".urlencode($_GET["searchtag"])."'>Diese Kategorie exportieren (HTML Overview)</a>";
	}
?>
</div>
