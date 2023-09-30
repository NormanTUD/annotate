<?php
	include("header.php");
	include_once("functions.php");
?>
	<script>
		function change_url () {
			var trs = $("#export_settings").find("tr");

			var url_pairs = [];

			for (var i = 1; i < trs.length; i++) {
				var children = $(trs[i]).children();

				var explanation = $(children[0]).html();
				var name = $(children[1]).html();
				var value = $(children[2]).find("input,checkbox").val();

				url_pairs.push(name + "=" + value);
			}

			var new_url = url_pairs.join("&");

			$("#link").attr("href", "export_annotations.php?" + new_url).show();
		}
	</script>

	<table id="export_settings" border=1>
		<tr>
			<th>Erklärung</th>
			<th>Option</th>
			<th>Wert</th>
		</tr>

		<tr>
			<td>Maximale Anzahl an Dateien (0 = kein Limit)</td>
			<td>max_files</td>
			<td><input onchange="change_url()" type="number" value="0" /></td>
		</tr>
	</table>

	<a id="link" style="display: none" href="">Download</a>

	<script>
		change_url();
	</script>
<?php
	include_once("footer.php");
?>
