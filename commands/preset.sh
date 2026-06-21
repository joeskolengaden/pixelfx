#!/bin/bash
# $1 = preset name. Reads the 'presets' setting (JSON managed by the UI) and
# POSTs each key/value of the named preset back to the plugin.
python3 - "$1" <<'PY'
import sys, json, subprocess
name = sys.argv[1]
raw = subprocess.check_output(["curl","-s","http://localhost/api/plugin/pixelfx/settings/presets"]).decode()
try:
    blob = json.loads(raw).get("presets","")
    presets = json.loads(blob) if blob else {}
except Exception:
    presets = {}
p = presets.get(name)
if not p:
    sys.exit(0)
for k, v in p.items():
    subprocess.run(["curl","-s","-X","POST","--data",str(v),
                    "http://localhost/api/plugin/pixelfx/settings/%s" % k])
PY
