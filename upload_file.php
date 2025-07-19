<?php
        include_once("functions.php");

        ini_set('memory_limit', '16384M');
        ini_set('max_execution_time', '3600');
        set_time_limit(3600);

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['image']['name'];
                $file_tmp = $_FILES['image']['tmp_name'];

                // Check if the file is a JPEG image
                $image_info = getimagesize($file_tmp);
                if ($image_info === false || $image_info['mime'] !== 'image/jpeg') {
                        echo "Error: Please upload a valid JPEG image.";
                } else {
                        try {
                                // Load original image
                                $src_image = imagecreatefromjpeg($file_tmp);

                                // Original dimensions
                                $orig_width = imagesx($src_image);
                                $orig_height = imagesy($src_image);

                                // Scale down to max 640x640 while keeping aspect ratio
                                $max_dim = 640;
                                $scale = min($max_dim / $orig_width, $max_dim / $orig_height, 1); // don't upscale
                                $new_width = (int)($orig_width * $scale);
                                $new_height = (int)($orig_height * $scale);

                                // Create resized image
                                $resized_image = imagecreatetruecolor($new_width, $new_height);
                                imagecopyresampled(
                                        $resized_image, $src_image,
                                        0, 0, 0, 0,
                                        $new_width, $new_height,
                                        $orig_width, $orig_height
                                );

                                // Save resized image to memory
                                ob_start();
                                imagejpeg($resized_image, null, 90); // quality 90
                                $resized_image_data = ob_get_clean();

                                // Free memory
                                imagedestroy($src_image);
                                imagedestroy($resized_image);

                                // Insert into DB
                                insert_image_into_db_from_data($resized_image_data, $file_name);

                                // Display the uploaded image
                                echo "<img style='max-height: 400px; max-width: 400px;' src='print_image.php?filename=".htmlentities(urlencode($file_name))."' alt='Uploaded Image'>";

                        } catch (\Throwable $e) {
                                echo "Error<Internal>: <pre>$e</pre>";
                        }
                }
        } else {
                echo "Error: Please select a valid JPEG image to upload.";
        }
?>
