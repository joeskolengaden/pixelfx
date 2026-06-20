#!/bin/bash
#
# pixelfx uninstall script. FPP removes the plugin directory itself; this just
# cleans up the plugin's settings file so an uninstall leaves nothing behind.

. ${FPPDIR}/scripts/common 2>/dev/null

CFG="${CFGDIR:-${MEDIADIR:-/home/fpp/media}/config}"
rm -f "${CFG}/plugin.pixelfx"

echo "pixelfx settings removed."
