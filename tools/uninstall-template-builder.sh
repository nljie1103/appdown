#!/bin/bash
set -euo pipefail
RUNNER="/usr/local/libexec/appdown-template-builder-runner"
if [ -x "$RUNNER" ]; then
  "$RUNNER" uninstall || true
else
  IMAGE="${APPDOWN_TEMPLATE_BUILDER_IMAGE:-appdown/template-builder:2}"
  command -v docker >/dev/null 2>&1 && docker image rm -f "$IMAGE" >/dev/null 2>&1 || true
fi
echo "[✓] 已移除 AppDown Template Builder 镜像。Docker Engine、AppDown 数据和签名文件均未删除。"
