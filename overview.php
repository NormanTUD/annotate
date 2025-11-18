<?php
	include("header.php");
	include_once("functions.php");

	function h($str) {
		if($str) {
			return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
		}

		return $str;
	}

	function fetchAll($query) {
		$stmt = $GLOBALS['dbh']->prepare($query);
		$stmt->execute();
		return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	}

	function renderTable($title, $headers, $rows, $actions = []) {
		echo "<h2>" . h($title) . "</h2>";
		echo "<table><tr>";
		foreach ($headers as $h) {
			echo "<th>" . h($h) . "</th>";
		}
		if ($actions) {
			echo "<th>Actions</th>";
		}
		echo "</tr>";

		foreach ($rows as $row) {
			echo "<tr>";
			foreach ($headers as $key) {
				echo "<td>" . h($row[$key]) . "</td>";
			}

			if ($actions) {
				echo "<td>";
				foreach ($actions as $action) {
					echo "<form method='post' action='" . h($action['target']) . "' data-ajax='true' data-confirm='" . h($action['confirm']) . "' style='display:inline'>
						<input type='hidden' name='id' value='" . h($row['id']) . "'>
						<input type='submit' class='btn' value='" . h($action['label']) . "'>
						</form> ";
				}
				echo "</td>";
			}

			echo "</tr>";
		}
		echo "</table>";
	}
?>

	<h1>Annotation Dashboard</h1>

<?php
	$models = fetchAll("SELECT * FROM models ORDER BY upload_time DESC");
	renderTable("Models", ['id', 'model_name', 'upload_time', 'filename', 'uid'], $models, [
		['target' => 'delete_model.php', 'label' => 'Delete', 'confirm' => 'Delete this model?']
	]);

	$images = fetchAll("SELECT id, filename, width, height, deleted, offtopic, unidentifiable, perception_hash FROM image ORDER BY id DESC LIMIT 100");
	foreach ($images as &$img) {
		$img['size'] = $img['width'] . "×" . $img['height'];
	}
	renderTable("Images", ['id', 'filename', 'size', 'deleted', 'offtopic', 'unidentifiable', 'perception_hash'], $images);

	$annots = fetchAll("
	    SELECT a.id, i.filename, u.name as user, c.name as category,
		   CONCAT('(', a.x_start, ',', a.y_start, ') ', a.w, '×', a.h) AS box,
		   a.modified, a.deleted
	    FROM annotation a
	    JOIN image i ON a.image_id = i.id
	    JOIN user u ON a.user_id = u.id
	    JOIN category c ON a.category_id = c.id
	    ORDER BY a.modified DESC LIMIT 100
	");
	renderTable("Annotations", ['id', 'filename', 'user', 'category', 'box', 'modified', 'deleted'], $annots);

	$annots_bare = fetchAll("
		select
			id,
			image_id,
			user_id,
			category_id,
			x_start,
			y_start,
			w,
			h,
			json,
			annotarius_id,
			modified,
			deleted
		from annotation
	");

	renderTable("Annotations (bare)", [
		"id",
		"image_id",
		"user_id",
		"category_id",
		"x_start",
		"y_start",
		"w",
		"h",
		"json",
		"annotarius_id",
		"modified",
		"deleted"
	], $annots_bare);

	$cats = fetchAll("SELECT * FROM category ORDER BY id");
	renderTable("Categories", ['id', 'name'], $cats, [
	    ['target' => 'delete_category.php', 'label' => 'Delete', 'confirm' => 'Delete this category?']
	]);

	$users = fetchAll("SELECT * FROM user ORDER BY id");
	renderTable("Users", ['id', 'name'], $users);

	include_once("footer.php");
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const deleteForms = document.querySelectorAll('form[data-ajax="true"]');

    deleteForms.forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!confirm(form.dataset.confirm)) return;

            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    window.location.reload();
                } else {
                    // Fehler ausgeben
                    alert("Error: " + result.message);
                }
            } catch (err) {
                console.error(err);
                alert("AJAX request failed.");
            }
        });
    });
});
</script>
