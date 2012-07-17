#!/bin/bash
wget -O "$1" "$2"?start="$3" 2>"$4"/err 1>"$4"/log

if [ $? -eq 0 ]
then
echo success
else
echo failure >&2
fi