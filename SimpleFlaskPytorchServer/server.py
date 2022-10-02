import os
import sys
import io
import json
import base64
import uuid
import pprint
import json
from flask import Flask, request
from PIL import Image
import torch
from flask_cors import CORS, cross_origin
import re

class REMatcher(object):
    def __init__(self, matchstring):
        self.matchstring = matchstring

    def match(self,regexp):
        self.rematch = re.match(regexp, self.matchstring)
        return bool(self.rematch)

    def group(self,i):
        return self.rematch.group(i)

def dier (msg):
    pprint.pprint(msg)

def debug (msg):
    print('Debug: ' + pprint.pformat(msg), file=sys.stderr)

app = Flask("Image analyzer")
CORS(app)

#model = torch.hub.load('ultralytics/yolov5', 'yolov5x')  # or yolov5n - yolov5x6, custom
# git clone --depth 1 https://github.com/ultralytics/yolov5.git
script_dir = os.path.dirname(os.path.realpath(__file__))
model = torch.hub.load(script_dir + "/yolov5", 'custom', path=script_dir+"/mehr.pt", source='local')

@app.route('/',  methods = ['GET'])
def index():
    return """
    <h2>Upload a file<h2>
    <form action="/" method="post" enctype="multipart/form-data">
        Select image to upload:
        <input type="file" name="image" id="image">
        <input type="submit" value="Upload Image" name="submit">
    </form>
    """

@app.route('/annotarious',  methods = ['POST'])
def reveice_ufo_image_annotarious():
    debug("Loading image file")

    d = json.loads(request.get_data(cache=False, as_text=True, parse_form_data=False))

    try:
        msg = base64.b64decode(d['image'])
        src = d["src"]
        buf = io.BytesIO(msg)
        img = Image.open(buf)
        #img = Image.open(cStringIO.StringIO(d['image']))
        debug("Running model")
        results = model(img)
        debug("Getting outputs")
        #return pprint.pformat(results.pandas().xywh[0])

        r = results.pandas().xywh[0]
        npa = results.pandas().xywh[0].to_numpy()

        debug("===================")
        debug(npa)
        debug("===================")

        part_strings = []
        #0                      1               2                   3                   4                 5     6
        #280.02264404296875, 85.78663635253906, 49.54310607910156, 58.41455841064453, 0.3505318760871887, 3, 'raketenspirale'

        for item in npa:
            xcenter = float(item[0])
            ycenter = float(item[2])
            width = float(item[3])
            height = float(item[4])
            confidence = float(item[4])
            name = item[6]

            xstart = xcenter - (width / 2)
            ystart = ycenter - (height / 2)

            debug(name)

            item_uuid = str(uuid.uuid4())

            ps = """ {
                "type": "Annotation",
                "body": [
                  {
                    "type": "TextualBody",
                    "value": "%s",
                    "purpose": "tagging"
                  }
                ],
                "target": {
                  "source": "%s",
                  "selector": {
                    "type": "FragmentSelector",
                    "conformsTo": "http://www.w3.org/TR/media-frags/",
                    "value": "xywh=pixel:%d,%d,%d,%d"
                  }
                },
                "@context": "http://www.w3.org/ns/anno.jsonld",
                "id": "#%s"
              }
            """ % (name, src, round(xstart), round(ystart), round(width), round(height), item_uuid)
            part_strings.append(ps)


        res_json = "[" + ", ".join(part_strings) + "]"

        #print(res_json)

        return res_json
    except Exception as e:
        debug(e)
        return str(e)

@app.route('/',  methods = ['POST'])
def reveice_ufo_image():
    debug("Loading image file")

    try:

        img = Image.open(request.files['image'].stream)
        debug("Running model")
        results = model(img)
        debug("Getting outputs")
        return pprint.pformat(results.pandas().xyxy[0])

    except Exception as e:
        debug(e)
        return str(e)


app.run(host='0.0.0.0', port=12000)
