#!/bin/sh
set -eu
exec python3 /opt/appdown/template_builder.py "$@"
