<?php
include_once("functions.php");
?>
<!DOCTYPE html>
<head>
	<title>Bildannotationstool</title>
	<link rel="stylesheet" href="annotorious.min.css">
	<link rel="stylesheet" href="toastr.min.css">
	<link rel="stylesheet" href="main.css">
	<script src="annotorious.min.js"></script>
	<script src="jquery.min.js"></script>
	<script src="main.js"></script>
	<script src="toastr.min.js"></script>
</head>

<body>

 <!-- Tab links -->
<div class="tab">
  <button class="tablinks" onclick="open_tab(event, 'tab_home')" id="defaultOpen" >Home</button>
  <button class="tablinks" onclick="open_tab(event, 'tab_export')">Export</button>
</div>

<!-- Tab content -->
<div id="tab_home" class="tabcontent">
<?php
print_home();
?>
</div>

<div id="tab_export" class="tabcontent">
  <a href="export_annotations.php">Annotationen exportieren</a>
<?php
	
	if(isset($_GET["searchtag"])) {
		print "<br><a href='export_annotations.php?show_categories[]=".urlencode($searchtag)."'>Diese Kategorie exportieren</a>";
		print "<br><a href='export_annotations.php?format=html&show_categories[]=".urlencode($searchtag)."'>Diese Kategorie exportieren (HTML Overview)</a>";
	}
?>
</div>
