#!/bin/bash

if [ ! -d "yolov5" ]; then
        git clone --depth 1 https://github.com/ultralytics/yolov5.git
fi

cd yolov5

if [[ ! -e .alpha_yoloenv_normal/bin/activate ]]; then
        python3 -mvenv .alpha_yoloenv_normal/
        source .alpha_yoloenv_normal/bin/activate
        pip3 install -r requirements.txt
	pip3 install tensorflowjs
fi

if [[ "$(python -c 'import numpy; print(numpy.__version__)')" =~ "1.26.0" ]]; then
	git clone https://github.com/numpy/numpy.git
	echo "compile yourself..."
fi

cd -

if [[ ! -e $1 ]]; then
	echo "$1 not found";
	exit 1
fi

WORK_DIR=`mktemp -d -p "$DIR"`
WEBMODEL="$WORK_DIR/model_web_model"
echo "Work-Dir: $WORK_DIR"
echo ">>PATH>>$WEBMODEL<<PATH<<"

export CUDA_VISIBLE_DEVICES=""

PATH="$PATH:$(pwd)/yolov5/.alpha_yoloenv_normal/bin/"

cp $1 $WORK_DIR/model.pt 2>&1

python3 yolov5/export.py --weights $WORK_DIR/model.pt --img 512 512 --batch-size 1 --include tfjs 2>&1

echo "export-exit-code: $?"
