#!/bin/bash

if [[ ! -e $1 ]]; then
	echo "$1 not found";
	exit 1
fi

if [ ! -d "yolov5" ]; then
        git clone --depth 1 https://github.com/ultralytics/yolov5.git
fi

cd yolov5

if [[ ! -e .alpha_yoloenv_normal/bin/activate ]]; then
        python3 -mvenv .alpha_yoloenv_normal/
        source .alpha_yoloenv_normal/bin/activate
        pip3 install -r requirements.txt
fi

cd -

python3 yolov5/export.py --weights $1 --img 512 512 --batch-size 1 --include tfjs
