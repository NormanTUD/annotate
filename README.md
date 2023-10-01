# SimpleImageAnnotationTool
A simple image annotation tool in PHP/JS

![Screenshot](screens/screen1.png "No AI")
![Screenshot](screens/screen2.png "AI")

# Run

Just run

```
git clone --depth 1 https://github.com/NormanTUD/annotate.git
cd annotate
bash docker.sh --local-port 3431
```

It should then run at port 3431.

# What it does

You can upload a bunch of images and annotate them for usage with YoloV5.

You can export them to the YoloV5-format as well.

It is made for large datasets and groups that voluntarily label data. It has a (yet incomplete) curation system.

It also allows you to upload TFJS-models and use the models in the browser to annotate further data.
