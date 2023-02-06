<?php
header("Access-Control-Allow-Origin: *");
include_once("functions.php");
?>
<!DOCTYPE html>
<head>
	<title>Bildannotationstool</title>
	<link rel="stylesheet" href="annotorious.min.css">
	<link rel="stylesheet" href="toastr.min.css">
	<link rel="stylesheet" href="main.css">
	<script src="plotly-latest.min.js"></script>
	<script src="annotorious.min.js"></script>
	<script src="jquery.min.js"></script>
	<script src="main.js"></script>
	<script src="toastr.min.js"></script>
	<script src='tf.js'></script>
	<script src='tf-backend-wasm.min.js'></script>
	<script>
var labels = [
	"halo",
"sonne",
"skytracker",
"light pillar",
"kondensstreifen",
"flugzeug",
"wing suit",
"raketenspirale",
"heißluftballon",
"sternschnuppe",
"ballon",
"zirkumhorizontalbogen",
"raketenabgas",
"sundog",
"lens flare",
"rauchring",
"lentikularwolke",
"stern",
"mond",
"vogel",
"insekt",
"fluctus wolke",
"leuchtende nachtwolken",
"mammatuswolke",
"irisierende wolke",
"morning glory cloud",
"sprite",
"fallschirm",
"solarballon",
"drachen",
"polarlicht",
"kugelwolke",
"regenbogen",
"folienballon",
"reentry",
"flogo",
"led-drohnen",
"hole punch cloud",
"blitz",
"ambosswolke",
"unfokussiertes objekt",
"starlink",
"satellit",
"elmsfeuer",
"paraglider",
"mülltüte",
"hubschrauber",
"planet",
"milchstraße",
"vogelschwarm",
"funken",
"sonnenstrahlen",
"himmelslaterne",
"bolid",
"flare",
"grüner blitz",
"fledermaus",
"halo mond",
"blue jet",
"crown flash",
"elve"
];
	</script>
</head>

<body>

 <!-- Tab links -->
<div class="tab">
  <button class="tablinks" onclick="open_tab(event, 'tab_home')" id="defaultOpen" >Home</button>
  <button class="tablinks" onclick="open_tab(event, 'tab_export')">Export</button>
</div>

<!-- Tab content -->
<div id="tab_home" class="tabcontent">
	<div id="tab_home_top"></div>
	<div id="memory_debugger"></div>
</div>

<div id="tab_export" class="tabcontent">
  <a href="export_annotations.php">Annotationen exportieren</a>
<?php
	
	if(isset($_GET["searchtag"])) {
		print "<br><a href='export_annotations.php?show_categories[]=".urlencode($_GET["searchtag"])."'>Diese Kategorie exportieren</a>";
		print "<br><a href='export_annotations.php?format=html&show_categories[]=".urlencode($_GET["searchtag"])."'>Diese Kategorie exportieren (HTML Overview)</a>";
	}
?>
</div>
