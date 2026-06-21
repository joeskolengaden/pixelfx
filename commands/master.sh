#!/bin/bash
# $1 = state. Turns the whole plugin on/off.
V=0; case "$1" in 1|true|on|On|ON) V=1;; esac
curl -s -X POST --data "$V" "http://localhost/api/plugin/pixelfx/settings/enabled" >/dev/null
