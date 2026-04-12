#!/bin/bash
#=====================================================
# iOS 构建环境卸载脚本
# 用法: sudo bash tools/uninstall-ios-env.sh
#=====================================================

set -e

CONTAINER_NAME="ysapp-ios-builder"
DOCKER_OSX_IMAGE="sickcodes/docker-osx:sonoma"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()   { echo -e "${GREEN}[✓]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }
error() { echo -e "${RED}[✗]${NC} $1"; }

# 计算项目根目录
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
BUILD_DIR="$PROJECT_DIR/data/ios-build"

if [ "$(id -u)" -ne 0 ]; then
    error "请使用 sudo 运行此脚本"
    exit 1
fi

echo ""
echo "==========================================="
echo "  iOS 构建环境卸载"
echo "==========================================="
echo ""

# Step 1: 停止并删除容器
echo "--- Step 1/3: 删除 macOS 容器 ---"
if docker ps -a --format '{{.Names}}' 2>/dev/null | grep -q "^${CONTAINER_NAME}$"; then
    log "停止容器 $CONTAINER_NAME ..."
    docker stop "$CONTAINER_NAME" 2>/dev/null || true
    log "删除容器 $CONTAINER_NAME ..."
    docker rm "$CONTAINER_NAME" 2>/dev/null || true
    log "容器已删除"
else
    warn "容器 $CONTAINER_NAME 不存在，跳过"
fi

# Step 2: 删除 Docker-OSX 镜像（可选，释放约20GB磁盘）
echo ""
echo "--- Step 2/3: 删除 Docker-OSX 镜像 ---"
if docker image inspect "$DOCKER_OSX_IMAGE" > /dev/null 2>&1; then
    log "删除镜像 $DOCKER_OSX_IMAGE（约 20GB）..."
    docker rmi "$DOCKER_OSX_IMAGE" 2>/dev/null || true
    log "镜像已删除"
else
    warn "镜像 $DOCKER_OSX_IMAGE 不存在，跳过"
fi

# Step 3: 删除共享构建目录
echo ""
echo "--- Step 3/3: 清理构建目录 ---"
if [ -d "$BUILD_DIR" ]; then
    log "删除构建目录 $BUILD_DIR ..."
    rm -rf "$BUILD_DIR"
    log "构建目录已删除"
else
    warn "构建目录不存在，跳过"
fi

echo ""
echo "==========================================="
echo -e "  ${GREEN}iOS 构建环境已完全卸载${NC}"
echo "==========================================="
echo ""
echo "  已删除: macOS 容器 ($CONTAINER_NAME)"
echo "  已删除: Docker-OSX 镜像 ($DOCKER_OSX_IMAGE)"
echo "  已删除: 构建目录 ($BUILD_DIR)"
echo ""
echo "  注意: Docker 本身未卸载（可能被其他服务使用）"
echo ""
