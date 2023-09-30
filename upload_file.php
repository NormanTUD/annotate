<?php
include_once("functions.php");

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file_name = $_FILES['image']['name'];
    $file_tmp = $_FILES['image']['tmp_name'];

    // Check if the file is a JPEG image
    $image_info = getimagesize($file_tmp);
    if ($image_info === false || $image_info['mime'] !== 'image/jpeg') {
        echo "Error: Please upload a valid JPEG image.";
    } else {
        // Call the function to insert the image into the database
        insert_image_into_db($file_tmp, $file_name);

        // Display the uploaded image
        echo "<img src='path_to_uploaded_images/$file_name' alt='Uploaded Image'>";
    }
} else {
    echo "Error: Please select a valid JPEG image to upload.";
}
?>
