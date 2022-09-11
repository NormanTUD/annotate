# SimpleImageAnnotationTool
A simple image annotation tool in PHP/JS

# Setup
Install Apache+PHP. Copy this dir to `/var/www/html/annotate/`.

```command
sudo mkdir tmp images annotations
sudo chown -R www-data:$USER tmp images annotations
```

# What it does

It Looks through the images in the images directory and the annotations in the annotations
directory. It displays one random image that has the least amount of annotations currently.

You can then draw boxes with the mouse and save them, and they get saved to an internal
file format.

Call the `export_annotations.php` to get a zip with all the annotations in CSV Format
with a dataset.yaml, nearly ready for training with YOLOv5.

Every user gets his own `user-id` (no login required, it's auto-generated and saved in a cookie),
so that one user can only see his own annotations.

# How to fill data
Just put JPG files in the images folder.

# How it will look like

![Alt Text](annotate.gif)
