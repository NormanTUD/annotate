#!/bin/bash

IMGPATH=$1

curl -F "image=@${IMGPATH}" https://ufo-ki.de
