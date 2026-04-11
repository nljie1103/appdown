#!/bin/bash
#=====================================================
# Android 构建环境卸载脚本
# 用法: sudo bash tools/uninstall-android-env.sh
#=====================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()   { echo -e "${GREEN}[✓]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }
error() { echo -e "${RED}[✗]${NC} $1"; }

ANDROID_HOME="/opt/android-sdk"

if [ "$(id -u)" -ne 0 ]; then
    error "请使用 sudo 运行此脚本"
    exit 1
fi

echo ""
echo "========================================="
echo "  Android 构建环境卸载"
echo "========================================="
echo ""

# Step 1: 删除 Android SDK
echo "--- Step 1/2: 删除 Android SDK ---"
if [ -d "$ANDROID_HOME" ]; then
    log "删除 $ANDROID_HOME ..."
    rm -rf "$ANDROID_HOME"
    log "Android SDK 已删除"
else
    warn "Android SDK 目录不存在，跳过"
fi

# Step 2: 卸载 JDK 17
echo ""
echo "--- Step 2/2: 卸载 OpenJDK 17 ---"
if dpkg -l | grep -q openjdk-17; then
    log "卸载 openjdk-17-jdk ..."
    apt-get remove -y -qq openjdk-17-jdk openjdk-17-jdk-headless openjdk-17-jre openjdk-17-jre-headless > /dev/null 2>&1 || true
    apt-get autoremove -y -qq > /dev/null 2>&1 || true
    log "OpenJDK 17 已卸载"
else
    warn "OpenJDK 17 未安装，跳过"
fi

echo ""
echo "========================================="
echo -e "  ${GREEN}Android 构建环境已完全卸载${NC}"
echo "========================================="
echo ""
echo "  已删除: Android SDK ($ANDROID_HOME)"
echo "  已卸载: OpenJDK 17"
echo ""
