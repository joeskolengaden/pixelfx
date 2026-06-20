#!/bin/bash
#
# FPP runs this with "--list" at startup. Printing "c++" tells FPP this is a
# compiled plugin and to load libpixelfx.so (built by scripts/fpp_install.sh).

case "$1" in
    --list)
        echo "c++"
        ;;
esac
