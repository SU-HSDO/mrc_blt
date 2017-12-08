#!/bin/bash

# Patch the htaccess file
cd docroot
patch -N -F100 -p1 <../patches/drupal/htaccess.patch
