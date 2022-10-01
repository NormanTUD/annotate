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
model = torch.hub.load("/var/www/html/annotate/SimpleFlaskPytorchServer/yolov5", 'custom', path="/var/www/html/annotate/SimpleFlaskPytorchServer/sterne.pt", source='local')

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
        dir(results.pandas())

        part_strings = []

        i = 0
        for line in str(r).splitlines():
            if i != 0:
                m = REMatcher(line)

                # 0  519.995667  77.812920  77.668060  72.964615    0.609547      0  stern
                if m.match(r"^\s*(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s+(.*)\s*$"):
                    nr = int(m.group(1))
                    xcenter = float(m.group(2))
                    ycenter = float(m.group(3))
                    width = float(m.group(4))
                    height = float(m.group(5))
                    confidence = float(m.group(6))
                    classnr = float(m.group(7))
                    name = m.group(8)

                    xstart = xcenter - (width / 2)
                    ystart = ycenter - (height / 2)


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

            i = i + 1


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
