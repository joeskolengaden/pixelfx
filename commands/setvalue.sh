#!/bin/bash
# $1 = setting key, $2 = value
curl -s -X POST --data "$2" "http://localhost/api/plugin/pixelfx/settings/$1" >/dev/null
