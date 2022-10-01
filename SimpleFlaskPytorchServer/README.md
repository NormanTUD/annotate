# A SimpleFlaskPytorchServer

What is this?

It allows you to run a python3-flask server that accepts incoming images, analyzes
them with a network from the Torch-Hub (yolo in the default case) and gives out a
CSV file with all the information needed for drawing bounding boxes.

# Run it like this:

```command
python3 serve.py
```

and you can test it like this (make sure the server runs first):

```command
bash test.sh /absolute/path/to/any_image.jpg
```

and the output will be like this:

```
          xmin         ymin         xmax         ymax  confidence  class  \
0     7.582725  1595.966919   882.295288  2441.919434    0.779987     62
1  1810.096924  2063.343994  3262.957764  2447.862061    0.385503     73
2  2826.222412  1677.988037  3103.447754  2074.812256    0.372476     56
3  1130.347290  2362.758301  1367.342529  2447.662598    0.350984     73
4  1819.499023  2069.544922  3263.154785  2443.905029    0.323654     63
5  2374.590576  1481.780029  2829.007324  1828.166260    0.286431     56
6  1259.363525  2063.048096  1458.264404  2152.036133    0.251384     73

     name
0      tv
1    book
2   chair
3    book
4  laptop
5   chair
6    book
```

# Dependencies

```command
sudo apt-get install ffmpeg libsm6 libxext6
pip3 install flask
pip3 install pandas
pip3 install torchvision
pip3 install matplotlib
pip3 install seaborn
pip3 install torch
pip3 install opencv-python
```
