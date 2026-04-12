#!/bin/bash
#=====================================================
# iOS 构建环境 - Xcode 安装脚本（Phase 2: 交互式）
# 用法: sudo bash tools/setup-ios-xcode.sh
#
# 此脚本需要在终端中交互式运行（需要输入 Apple ID + 2FA）
# 不要通过 Web 后台触发！
#=====================================================

set -e

SSH_PORT=50922
CONTAINER_NAME="ysapp-ios-builder"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()   { echo -e "${GREEN}[✓]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }
error() { echo -e "${RED}[✗]${NC} $1"; }

echo ""
echo "==========================================="
echo "  Xcode 安装向导 (交互式)"
echo "==========================================="
echo ""
echo "此脚本将通过 SSH 连接到 macOS 容器，使用 xcodes CLI 安装 Xcode。"
echo "安装过程需要输入 Apple ID 和两步验证码。"
echo ""

# 检查容器是否运行
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    error "macOS 容器 $CONTAINER_NAME 未运行"
    echo "  请先执行 Phase 1: sudo bash tools/setup-ios-env.sh"
    exit 1
fi

# 检查 SSH 连接
if ! ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -p $SSH_PORT user@localhost "echo ok" 2>/dev/null | grep -q "ok"; then
    error "无法通过 SSH 连接到 macOS 容器"
    echo "  请确认容器已完成启动"
    exit 1
fi

log "SSH 连接成功"
echo ""

# 检查 xcodes 是否已安装
XCODES_OK=$(ssh -o StrictHostKeyChecking=no -p $SSH_PORT user@localhost "command -v xcodes > /dev/null 2>&1 && echo yes || echo no" 2>/dev/null)

if [ "$XCODES_OK" != "yes" ]; then
    log "xcodes CLI 未安装，正在安装 ..."
    ssh -o StrictHostKeyChecking=no -p $SSH_PORT user@localhost bash -c '
        if ! command -v brew > /dev/null 2>&1; then
            eval "$(/opt/homebrew/bin/brew shellenv)" 2>/dev/null || true
        fi
        brew install xcodesorg/made/xcodes
    '
    log "xcodes CLI 安装完成"
fi

echo ""
echo "========================================="
echo "  即将进入 macOS 容器安装 Xcode"
echo "========================================="
echo ""
echo "  进入后将自动执行 xcodes install --latest"
echo "  您需要输入 Apple ID 和 两步验证码"
echo ""
echo "  注意: Xcode 下载约 12GB，安装需要较长时间"
echo ""
read -p "按 Enter 继续..."

# 连接到容器执行 Xcode 安装
ssh -t -o StrictHostKeyChecking=no -p $SSH_PORT user@localhost bash -c '
    # 确保 brew 在 PATH 中
    eval "$(/opt/homebrew/bin/brew shellenv)" 2>/dev/null || true

    echo ""
    echo "开始安装最新版 Xcode ..."
    echo ""

    xcodes install --latest

    echo ""
    echo "接受 Xcode 许可协议 ..."
    sudo xcodebuild -license accept 2>/dev/null || true

    echo ""
    echo "验证 Xcode 安装 ..."
    xcodebuild -version

    echo ""
    echo "Xcode 安装完成！"
'

RESULT=$?
echo ""
if [ $RESULT -eq 0 ]; then
    echo "==========================================="
    echo -e "  ${GREEN}Xcode 安装完成！${NC}"
    echo "==========================================="
    echo ""
    echo "  iOS 构建环境已就绪。"
    echo "  请回到后台「系统信息」页面点击「验证 Xcode」确认。"
else
    echo "==========================================="
    echo -e "  ${YELLOW}Xcode 安装可能未完成${NC}"
    echo "==========================================="
    echo ""
    echo "  您可以手动 SSH 进入容器重试："
    echo "    ssh -p $SSH_PORT user@localhost"
    echo "    xcodes install --latest"
    echo "    sudo xcodebuild -license accept"
fi
