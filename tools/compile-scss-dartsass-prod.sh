#!/bin/sh

common_arguments="--style expanded --no-source-map --load-path ./"

sass ${common_arguments} assets/scss-randley/:assets/css-randley/
