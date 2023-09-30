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

WORK_DIR=`mktemp -d -p "$DIR"`
echo "Work-Dir: $WORK_DIR"

cp $1 $WORK_DIR/model.pt

python3 yolov5/export.py --weights $WORK_DIR/model.pt --img 512 512 --batch-size 1 --include tfjs
exit_code=$?

if [[ $exit_code -eq 0 ]]; then
	rm $WORK_DIR/model.pt
fi

echo "$WORK_DIR/model_web_model"

echo "ls -1 $WORK_DIR/model_web_model"
ls -1 "$WORK_DIR/model_web_model"

exit $exit_code
