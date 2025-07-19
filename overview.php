<?php
	include("header.php");
	include_once("functions.php");


function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function fetchAll($query) {
    $stmt = $GLOBALS['dbh']->prepare($query);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Annotation Overview</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        h2 { margin-top: 40px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
        th, td { border: 1px solid #aaa; padding: 8px; }
        th { background: #f0f0f0; }
        form { display: inline; }
        input[type="text"] { width: 100px; }
        textarea { width: 90%; height: 50px; }
        .btn { padding: 4px 8px; }
    </style>
</head>
<body>

<h1>Annotation Dashboard</h1>

<h2>Models</h2>
<table>
    <tr><th>ID</th><th>Name</th><th>Uploaded</th><th>Filename</th><th>UID</th><th>Actions</th></tr>
    <?php
    $models = fetchAll("SELECT * FROM models ORDER BY upload_time DESC");
    foreach ($models as $m) {
        echo "<tr>
            <td>".h($m['id'])."</td>
            <td>".h($m['model_name'])."</td>
            <td>".h($m['upload_time'])."</td>
            <td>".h($m['filename'])."</td>
            <td>".h($m['uid'])."</td>
            <td>
                <form method='post' action='delete_model.php' onsubmit='return confirm(\"Delete this model?\")'>
                    <input type='hidden' name='id' value='".h($m['id'])."'>
                    <input type='submit' class='btn' value='Delete'>
                </form>
            </td>
        </tr>";
    }
    ?>
</table>

<h2>Images</h2>
<table>
    <tr><th>ID</th><th>Filename</th><th>Size</th><th>Deleted</th><th>Offtopic</th><th>Unidentifiable</th><th>Hash</th></tr>
    <?php
    $images = fetchAll("SELECT id, filename, width, height, deleted, offtopic, unidentifiable, perception_hash FROM image ORDER BY id DESC LIMIT 100");
    foreach ($images as $img) {
        echo "<tr>
            <td>".h($img['id'])."</td>
            <td>".h($img['filename'])."</td>
            <td>".h($img['width'])."×".h($img['height'])."</td>
            <td>".h($img['deleted'])."</td>
            <td>".h($img['offtopic'])."</td>
            <td>".h($img['unidentifiable'])."</td>
            <td>".h($img['perception_hash'])."</td>
        </tr>";
    }
    ?>
</table>

<h2>Annotations</h2>
<table>
    <tr><th>ID</th><th>Image</th><th>User</th><th>Category</th><th>Box</th><th>Modified</th><th>Deleted</th></tr>
    <?php
    $stmt = $GLOBALS['dbh']->prepare("
        SELECT a.id, i.filename, u.name as user, c.name as category, 
               a.x_start, a.y_start, a.w, a.h, a.modified, a.deleted
        FROM annotation a
        JOIN image i ON a.image_id = i.id
        JOIN user u ON a.user_id = u.id
        JOIN category c ON a.category_id = c.id
        ORDER BY a.modified DESC LIMIT 100
    ");
    $stmt->execute();
    $annots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($annots as $a) {
        echo "<tr>
            <td>".h($a['id'])."</td>
            <td>".h($a['filename'])."</td>
            <td>".h($a['user'])."</td>
            <td>".h($a['category'])."</td>
            <td>(".h($a['x_start']).",".h($a['y_start']).") ".h($a['w'])."×".h($a['h'])."</td>
            <td>".h($a['modified'])."</td>
            <td>".h($a['deleted'])."</td>
        </tr>";
    }
    ?>
</table>

<h2>Categories</h2>
<table>
    <tr><th>ID</th><th>Name</th><th>Actions</th></tr>
    <?php
    $cats = fetchAll("SELECT * FROM category ORDER BY id");
    foreach ($cats as $c) {
        echo "<tr>
            <td>".h($c['id'])."</td>
            <td>".h($c['name'])."</td>
            <td>
                <form method='post' action='delete_category.php' onsubmit='return confirm(\"Delete this category?\")'>
                    <input type='hidden' name='id' value='".h($c['id'])."'>
                    <input type='submit' class='btn' value='Delete'>
                </form>
            </td>
        </tr>";
    }
    ?>
</table>

<h2>Users</h2>
<table>
    <tr><th>ID</th><th>Name</th></tr>
    <?php
    $users = fetchAll("SELECT * FROM user ORDER BY id");
    foreach ($users as $u) {
        echo "<tr><td>".h($u['id'])."</td><td>".h($u['name'])."</td></tr>";
    }
    ?>
</table>

</body>
</html>
