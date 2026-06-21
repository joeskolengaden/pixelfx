#!/bin/bash
# $1 = function prefix (mr/hs/sa/co/br/sp/st/fr), $2 = state
V=0; case "$2" in 1|true|on|On|ON) V=1;; esac
curl -s -X POST --data "$V" "http://localhost/api/plugin/pixelfx/settings/${1}_enabled" >/dev/null
