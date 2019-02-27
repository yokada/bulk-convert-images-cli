#!/bin/sh

cd `dirname $0`
workdir=`pwd`

mkdir -p ${workdir}/images

for i in {1..100}
do
  echo ${workdir}/images/${i}.png
  convert -size 800x300 xc:white -pointsize 72 -fill black -draw "text 30,65 '${i}'" ${workdir}/images/${i}.png
done
