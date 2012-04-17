#!/bin/bash
HandBrakeCLI -i $1 -o $2  -e x264 -q 20.0 -a 1 -E faac -B 128 -6 dpl2 -O -R Auto -D 0.0 -f mp4 -X 720 -m -x cabac=0:ref=2:me=umh:bframes=0:weightp=0:subme=6:8x8dct=0:trellis=0 2>$3/err 1>$3/log

if [ $? -eq 0 ]
then
echo success
else
echo failure >&2
fi

