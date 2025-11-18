<?php
include("header.php");
include_once("functions.php");

// Löschen eines Modells
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
            print " <td>".$available_models[$i][0]."</td>\n";
            print " <td>".$available_models[$i][1]."</td>\n";
            print " <td><a href='models.php?delete_model=".$available_models[$i][1]."'>Delete!</a></td>\n";
            print "</tr>\n";
        }
?>
    </table>
<?php
}
?>

<h2>Or convert existing PyTorch model</h2>

<p>Select a local .pt file and provide a model name. The server will convert it to TFJS automatically.</p>

<form enctype="multipart/form-data" method="POST" action="upload_tfjs_model.php">
    <input type="file" name="pt_model_file" accept=".pt" required>
    <input type="text" name="model_name" placeholder="Model Name" required>
    <input type="submit" value="Convert and Upload Model">
</form>

<?php
include_once("footer.php");
?>
