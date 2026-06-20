#!/bin/bash
#
# Run by FPP after the plugin is installed. Compiles the plugin shared library.

set -e

PLUGINDIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PLUGINDIR"

FPPDIR="${FPPDIR:-/opt/fpp}"

echo "Building pixelfx plugin (FPPDIR=$FPPDIR)..."
make clean FPPDIR="$FPPDIR" || true
make FPPDIR="$FPPDIR"

echo "pixelfx plugin build complete. Restart fppd to load it."
