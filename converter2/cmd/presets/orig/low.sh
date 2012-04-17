#!/bin/bash
HandBrakeCLI -i $1 -o $2  -e x264 -q 20.0 -b 700 -a 1 -E faac -B 128 -6 dpl2 -O -R Auto -D 0.0 -f mp4 -I -X 480 -m -x level=30:bframes=0:weightp=0:cabac=0:ref=1:vbv-maxrate=768:vbv-bufsize=2000:analyse=all:me=umh:no-fast-pskip=1:subme=6:8x8dct=0:trellis=0 2>$3/err 1>$3/log

if [ $? -eq 0 ]
then
echo success
else
echo failure >&2
fi

