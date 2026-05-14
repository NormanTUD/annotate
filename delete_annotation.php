<?php
    include_once("functions.php");

    if(array_key_exists("source", $_POST)) {
        if(array_key_exists("id", $_POST)) {
            $annotate_userid = $_COOKIE["annotate_userid"];
            $user_id = get_or_create_user_id($annotate_userid);

            $raw_id = $_POST["id"];
            $affected = 0;

            // Try 1: exact match (as-is)
            flag_deleted($raw_id);
            $affected = mysqli_affected_rows($GLOBALS['dbh']);

            // Try 2: strip leading '#'
            if ($affected === 0 && strpos($raw_id, '#') === 0) {
                $stripped = substr($raw_id, 1);
                flag_deleted($stripped);
                $affected = mysqli_affected_rows($GLOBALS['dbh']);
            }

            // Try 3: add leading '#'
            if ($affected === 0 && strpos($raw_id, '#') !== 0) {
                flag_deleted('#' . $raw_id);
                $affected = mysqli_affected_rows($GLOBALS['dbh']);
            }

            // Try 4: maybe data-id is the numeric DB primary key
            if ($affected === 0 && is_numeric($raw_id)) {
                $query = "DELETE FROM annotation WHERE id = " . intval($raw_id);
                rquery($query);
                $affected = mysqli_affected_rows($GLOBALS['dbh']);
            }

            print("OK Deleted ($affected rows affected, raw_id=$raw_id)");
        } else {
            die("No ID given");
        }
    } else {
        die("No source given");
    }
?>
