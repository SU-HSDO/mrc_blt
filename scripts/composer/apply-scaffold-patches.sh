#!/bin/bash

cd docroot
patch -N -F100 -p1 <../patches/htaccess.patch