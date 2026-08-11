#!/bin/bash
set -euo pipefail
CONTAINER_NAME="${CONTAINER_NAME:-ysapp-ios-builder}"
DOCKER_OSX_IMAGE="${DOCKER_OSX_IMAGE:-sickcodes/docker-osx:auto}"
REMOVE_DOCKER_OSX_IMAGE="${REMOVE_DOCKER_OSX_IMAGE:-0}"
[ "$(id -u)" -eq 0 ] || { echo "请使用 sudo 运行"; exit 1; }
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"; PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
if docker ps -a --format '{{.Names}}' 2>/dev/null | grep -qx "$CONTAINER_NAME"; then docker rm -f "$CONTAINER_NAME" >/dev/null; fi
rm -rf "$PROJECT_DIR/data/ios-build"
rm -f "$PROJECT_DIR/data/ios_known_hosts"
if [ "$REMOVE_DOCKER_OSX_IMAGE" = "1" ] && docker image inspect "$DOCKER_OSX_IMAGE" >/dev/null 2>&1; then docker rmi "$DOCKER_OSX_IMAGE" || true; fi
echo "iOS Builder 容器与临时构建数据已清理。Docker 本身和镜像默认保留，避免误删其他服务共享资源。"
