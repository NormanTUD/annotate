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
					echo "<form class='ajax-delete' method='post' action='" . h($action['target']) . "' data-confirm='" . h($action['confirm']) . "' style='display:inline'>
						<input type='hidden' name='id' value='" . h($row['id']) . "'>
						<button type='submit' class='btn'>" . h($action['label']) . "</button>
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
	renderTable("Models", ['id', 'model_name', 'upload_time', 'filename', 'uid'], $models);

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
<!-- Confirmation Modal -->
<div id="confirmModal" class="modal" style="display:none;">
  <div class="modal-content">
    <p id="confirmMessage"></p>
    <button id="confirmYes">Yes</button>
    <button id="confirmNo">No</button>
  </div>
</div>

<!-- Info Modal -->
<div id="infoModal" class="modal" style="display:none;">
  <div class="modal-content">
    <p id="infoMessage"></p>
    <button id="infoOk">OK</button>
  </div>
</div>

<style>
.modal {
  position: fixed;
  top:0; left:0; right:0; bottom:0;
  background: rgba(0,0,0,0.5);
  display: flex; justify-content: center; align-items: center;
  z-index: 9999;
}
.modal-content {
  background: black;
  color: white;
  padding: 20px;
  border-radius: 8px;
  text-align: center;
}
.modal-content button {
  margin: 5px;
  padding: 5px 15px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const deleteForms = document.querySelectorAll('form.ajax-delete');

    const confirmModal = document.getElementById('confirmModal');
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmYes = document.getElementById('confirmYes');
    const confirmNo = document.getElementById('confirmNo');

    const infoModal = document.getElementById('infoModal');
    const infoMessage = document.getElementById('infoMessage');
    const infoOk = document.getElementById('infoOk');

    let currentForm = null;

    deleteForms.forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault(); // **Abfangen!**
            currentForm = form;

            confirmMessage.textContent = form.dataset.confirm;
            confirmModal.style.display = 'flex';
        });
    });

    confirmYes.addEventListener('click', async () => {
        if (!currentForm) return;
        confirmModal.style.display = 'none';

        const formData = new FormData(currentForm);

        try {
            const response = await fetch(currentForm.action, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            infoMessage.textContent = result.message;
            infoModal.style.display = 'flex';

            infoOk.onclick = () => {
                infoModal.style.display = 'none';
                if(result.success) window.location.reload();
            };
        } catch (err) {
            console.error(err);
            infoMessage.textContent = "AJAX request failed.";
            infoModal.style.display = 'flex';
            infoOk.onclick = () => infoModal.style.display = 'none';
        }
    });

    confirmNo.addEventListener('click', () => {
        confirmModal.style.display = 'none';
    });
});
</script>
