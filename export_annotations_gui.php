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
				var value_item = $(children[2]).find("input,checkbox");

				var value = "";
				if(value_item.attr("type") == "checkbox") {
					value = value_item.is(":checked") ? 1 : 0;
				} else {
					value = value_item.val();
				}

				url_pairs.push(name + "=" + value);
			}

			var new_url = url_pairs.join("&");

			$("#link").attr("href", "export_annotations.php?" + new_url).show();
		}
	</script>

	<table id="export_settings" border=1>
		<tr>
			<th>Erklärung</th>
			<th style="display: none">Option</th>
			<th>Wert</th>
		</tr>

		<tr>
			<td>Maximale Anzahl an Dateien (0 = kein Limit)</td>
			<td style="display: none">max_files</td>
			<td><input onchange="change_url()" type="number" value="0" /></td>
		</tr>

		<tr>
			<td>Validation Split Size</td>
			<td style="display: none">validation_split</td>
			<td><input onchange="change_url()" type="number" value="0" /></td>
		</tr>

		<tr>
			<td>Test Split Size</td>
			<td style="display: none">test_split</td>
			<td><input onchange="change_url()" type="number" value="0" /></td>
		</tr>

		<tr>
			<td>Empty?</td>
			<td style="display: none">empty</td>
			<td><input onchange="change_url()" type="checkbox" /></td>
		</tr>

		<tr>
			<td>Group by perception hash?</td>
			<td style="display: none">group_by_perception_hash</td>
			<td><input onchange="change_url()" type="checkbox" /></td>
		</tr>

		<tr>
			<td>Only curated?</td>
			<td style="display: none">only_curated</td>
			<td><input onchange="change_url()" type="checkbox" checked /></td>
		</tr>

		<tr>
			<td>Only uncurated?</td>
			<td style="display: none">only_uncurated</td>
			<td><input onchange="change_url()" type="checkbox" /></td>
		</tr>

		<tr>
			<td>Max perception hash truncation?</td>
			<td style="display: none">max_truncation</td>
			<td><input onchange="change_url()" type="number" value="100" /></td>
		</tr>
	</table>

	<a id="link" style="display: none" href="">Download</a>

	<script>
		change_url();
	</script>
<?php
	include_once("footer.php");
?>
