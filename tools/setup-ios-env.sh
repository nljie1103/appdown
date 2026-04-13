#!/bin/bash
#=====================================================
# iOS 构建环境一键部署脚本（Phase 1: Docker + macOS 容器）
# 适用于 Linux 宿主机（需要 KVM 支持）
# 用法: sudo bash tools/setup-ios-env.sh
#
# 此脚本安装 Docker 并拉取 Docker-OSX 镜像，创建持久化 macOS 容器。
# Xcode 安装需要 Apple ID + 2FA 交互，请使用 setup-ios-xcode.sh 在终端中完成。
#=====================================================

set -e

# ========== 配置（可通过环境变量覆盖） ==========
DOCKER_OSX_IMAGE="${DOCKER_OSX_IMAGE:-sickcodes/docker-osx:sonoma}"
CONTAINER_NAME="${CONTAINER_NAME:-ysapp-ios-builder}"
SSH_PORT="${SSH_PORT:-50922}"
DOCKER_DATA_ROOT="${DOCKER_DATA_ROOT:-}"
DOCKER_MIRROR="${DOCKER_MIRROR:-}"
BUILD_DIR=""  # 运行时根据脚本位置计算

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()   { echo -e "${GREEN}[✓]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }
error() { echo -e "${RED}[✗]${NC} $1"; }

# ========== 前置检查 ==========
if [ "$(id -u)" -ne 0 ]; then
    error "请使用 sudo 运行此脚本"
    echo "  sudo bash $0"
    exit 1
fi

# 计算项目根目录（脚本在 tools/ 下）
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
BUILD_DIR="$PROJECT_DIR/data/ios-build"

echo ""
echo "==========================================="
echo "  iOS 构建环境一键部署 (Docker-OSX)"
echo "==========================================="
echo ""
echo "将安装以下组件："
echo "  - Docker CE（如未安装）"
echo "  - Docker-OSX 镜像: $DOCKER_OSX_IMAGE"
echo "  - 持久化 macOS 容器 ($CONTAINER_NAME, SSH端口 $SSH_PORT)"
[ -n "$DOCKER_DATA_ROOT" ] && echo "  - Docker 数据目录: $DOCKER_DATA_ROOT"
[ -n "$DOCKER_MIRROR" ] && echo "  - Docker 镜像加速: $DOCKER_MIRROR"
echo ""
echo "项目目录: $PROJECT_DIR"
echo "构建目录: $BUILD_DIR"
echo ""

# ========== Step 1: 检查 KVM ==========
echo "--- Step 1/5: 检查 KVM 虚拟化支持 ---"

if [ -e /dev/kvm ]; then
    log "KVM 可用"
else
    error "KVM 不可用！"
    echo ""
    echo "  Docker-OSX 依赖 KVM 硬件虚拟化。"
    echo "  请确保："
    echo "    1. 服务器 CPU 支持虚拟化（VT-x / AMD-V）"
    echo "    2. BIOS 中已开启虚拟化"
    echo "    3. 已安装 KVM 模块: sudo apt install qemu-kvm"
    echo "    4. 当前用户在 kvm 组: sudo usermod -aG kvm \$(whoami)"
    echo ""
    echo "  如果是云服务器（如阿里云/腾讯云），需要使用支持嵌套虚拟化的机型。"
    exit 1
fi

# ========== Step 2: 安装 Docker CE ==========
echo ""
echo "--- Step 2/5: 安装 Docker CE ---"

if command -v docker > /dev/null 2>&1; then
    DOCKER_VER=$(docker --version 2>/dev/null | head -1)
    log "Docker 已安装: $DOCKER_VER"
else
    log "正在安装 Docker CE ..."

    # 安装依赖
    apt-get update -qq
    apt-get install -y -qq ca-certificates curl gnupg > /dev/null 2>&1

    # 添加 Docker GPG key
    install -m 0755 -d /etc/apt/keyrings
    if curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc 2>/dev/null; then
        log "Docker GPG key 添加成功（官方源）"
    elif curl -fsSL https://mirrors.aliyun.com/docker-ce/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc 2>/dev/null; then
        log "Docker GPG key 添加成功（阿里云镜像）"
    else
        error "无法获取 Docker GPG key"
        exit 1
    fi
    chmod a+r /etc/apt/keyrings/docker.asc

    # 添加 Docker 源（尝试官方，失败则用阿里云镜像）
    . /etc/os-release
    ARCH=$(dpkg --print-architecture)
    if curl -s --connect-timeout 5 https://download.docker.com/linux/ubuntu/dists/$VERSION_CODENAME/Release > /dev/null 2>&1; then
        echo "deb [arch=$ARCH signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $VERSION_CODENAME stable" > /etc/apt/sources.list.d/docker.list
        log "使用 Docker 官方源"
    else
        echo "deb [arch=$ARCH signed-by=/etc/apt/keyrings/docker.asc] https://mirrors.aliyun.com/docker-ce/linux/ubuntu $VERSION_CODENAME stable" > /etc/apt/sources.list.d/docker.list
        log "使用阿里云 Docker 镜像源"
    fi

    apt-get update -qq
    apt-get install -y -qq docker-ce docker-ce-cli containerd.io > /dev/null 2>&1

    if command -v docker > /dev/null 2>&1; then
        log "Docker CE 安装成功"
    else
        error "Docker CE 安装失败"
        exit 1
    fi
fi

# 确保 Docker 服务运行
if ! systemctl is-active --quiet docker; then
    log "启动 Docker 服务 ..."
    systemctl start docker
    systemctl enable docker
fi
log "Docker 服务运行中"

# 配置 Docker daemon（数据目录 + 镜像加速）
if [ -n "$DOCKER_DATA_ROOT" ] || [ -n "$DOCKER_MIRROR" ]; then
    log "正在配置 Docker daemon.json ..."
    python3 - "$DOCKER_DATA_ROOT" "$DOCKER_MIRROR" << 'PYSCRIPT'
import json, sys, os
daemon_file = "/etc/docker/daemon.json"
cfg = {}
if os.path.exists(daemon_file):
    try:
        with open(daemon_file) as f:
            cfg = json.load(f)
    except (json.JSONDecodeError, IOError):
        cfg = {}
data_root = sys.argv[1]
mirror = sys.argv[2]
changed = False
if data_root:
    os.makedirs(data_root, exist_ok=True)
    if cfg.get("data-root") != data_root:
        cfg["data-root"] = data_root
        changed = True
        print(f"  data-root -> {data_root}")
if mirror:
    urls = [u.strip() for u in mirror.split(",") if u.strip()]
    if cfg.get("registry-mirrors") != urls:
        cfg["registry-mirrors"] = urls
        changed = True
        print(f"  registry-mirrors -> {urls}")
if changed:
    with open(daemon_file, "w") as f:
        json.dump(cfg, f, indent=2)
    print("daemon.json 已更新")
else:
    print("daemon.json 无需更改")
PYSCRIPT
    if [ $? -eq 0 ]; then
        log "Docker daemon.json 配置完成"
        # 重启 Docker 使配置生效
        log "重启 Docker 服务 ..."
        systemctl restart docker
        sleep 2
        if systemctl is-active --quiet docker; then
            log "Docker 服务重启成功"
        else
            warn "Docker 服务重启失败，请手动检查 /etc/docker/daemon.json"
        fi
    else
        warn "daemon.json 配置失败（python3 不可用？），跳过"
    fi
fi

# ========== Step 3: 拉取 Docker-OSX 镜像 ==========
echo ""
echo "--- Step 3/5: 拉取 Docker-OSX 镜像 ---"

if docker image inspect "$DOCKER_OSX_IMAGE" > /dev/null 2>&1; then
    log "Docker-OSX 镜像已存在，跳过拉取"
else
    log "正在拉取 $DOCKER_OSX_IMAGE（约 20GB，请耐心等待）..."
    if docker pull "$DOCKER_OSX_IMAGE"; then
        log "Docker-OSX 镜像拉取成功"
    else
        error "Docker-OSX 镜像拉取失败"
        echo "  请检查网络连接，或手动执行: docker pull $DOCKER_OSX_IMAGE"
        exit 1
    fi
fi

# ========== Step 4: 创建持久化容器 ==========
echo ""
echo "--- Step 4/5: 创建 macOS 容器 ---"

# 创建共享构建目录
mkdir -p "$BUILD_DIR"
chmod 755 "$BUILD_DIR"

if docker ps -a --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    # 容器已存在
    if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
        log "容器 $CONTAINER_NAME 已在运行中"
    else
        log "容器 $CONTAINER_NAME 已存在，启动中 ..."
        docker start "$CONTAINER_NAME"
        log "容器已启动"
    fi
else
    log "创建新容器 $CONTAINER_NAME ..."
    docker run -d \
        --name "$CONTAINER_NAME" \
        --device /dev/kvm \
        -p ${SSH_PORT}:10022 \
        -v "$BUILD_DIR:/mnt/build" \
        -e HEADLESS=1 \
        -e RAM=8 \
        -e EXTRA="-virtfs local,path=/mnt/build,mount_tag=build,security_model=passthrough,id=build" \
        "$DOCKER_OSX_IMAGE"

    if [ $? -eq 0 ]; then
        log "容器创建成功"
    else
        error "容器创建失败"
        exit 1
    fi
fi

# ========== Step 5: 等待 macOS 启动并验证 ==========
echo ""
echo "--- Step 5/5: 等待 macOS 启动 ---"

log "macOS 正在启动（首次启动可能需要 5-10 分钟）..."

MAX_WAIT=600  # 10分钟超时
ELAPSED=0
SSH_OK=false

while [ $ELAPSED -lt $MAX_WAIT ]; do
    if ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 -o BatchMode=yes -p $SSH_PORT user@localhost "echo ok" 2>/dev/null | grep -q "ok"; then
        SSH_OK=true
        break
    fi
    sleep 10
    ELAPSED=$((ELAPSED + 10))
    echo "  等待中... (${ELAPSED}s / ${MAX_WAIT}s)"
done

if [ "$SSH_OK" = true ]; then
    log "SSH 连接成功"
else
    warn "SSH 连接超时（${MAX_WAIT}秒）"
    echo ""
    echo "  macOS 可能仍在启动中。您可以稍后手动检查："
    echo "    ssh -p $SSH_PORT user@localhost"
    echo ""
    echo "  或在后台「系统信息」页面点击验证按钮。"
    echo ""
    echo "  容器已创建成功，后续步骤可以在 macOS 就绪后执行。"
fi

# 如果 SSH 可达，安装基础工具
if [ "$SSH_OK" = true ]; then
    log "安装基础工具（Homebrew + xcodes）..."
    ssh -o StrictHostKeyChecking=no -p $SSH_PORT user@localhost bash -s << 'REMOTE_EOF'
        # 安装 Homebrew（如未安装）
        if ! command -v brew > /dev/null 2>&1; then
            /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)" < /dev/null
            echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' >> ~/.zprofile
            eval "$(/opt/homebrew/bin/brew shellenv)"
        fi

        # 安装 xcodes CLI（如未安装）
        if ! command -v xcodes > /dev/null 2>&1; then
            brew install xcodesorg/made/xcodes
        fi

        echo "基础工具安装完成"
REMOTE_EOF

    if [ $? -eq 0 ]; then
        log "基础工具安装成功"
    else
        warn "基础工具安装未完成，可稍后手动安装"
    fi
fi

# ========== 验证总结 ==========
echo ""
echo "==========================================="
echo "  环境验证"
echo "==========================================="

PASS=true

# Docker
if command -v docker > /dev/null 2>&1; then
    log "Docker .............. OK"
else
    error "Docker .............. FAILED"
    PASS=false
fi

# Docker 运行中
if systemctl is-active --quiet docker; then
    log "Docker 服务 ........ OK"
else
    error "Docker 服务 ........ FAILED"
    PASS=false
fi

# KVM
if [ -e /dev/kvm ]; then
    log "KVM ................ OK"
else
    error "KVM ................ FAILED"
    PASS=false
fi

# 容器存在
if docker ps -a --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    log "容器已创建 ......... OK"
else
    error "容器已创建 ......... FAILED"
    PASS=false
fi

# 容器运行中
if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    log "容器运行中 ......... OK"
else
    error "容器运行中 ......... FAILED"
    PASS=false
fi

# SSH
if [ "$SSH_OK" = true ]; then
    log "SSH 连接 ........... OK"
else
    warn "SSH 连接 ........... 待验证"
fi

echo ""
if [ "$PASS" = true ]; then
    echo "==========================================="
    echo -e "  ${GREEN}Phase 1 安装完成！${NC}"
    echo "==========================================="
    echo ""
    echo "  下一步: 在服务器终端执行 Xcode 安装（需要 Apple ID）"
    echo "    sudo bash $SCRIPT_DIR/setup-ios-xcode.sh"
    echo ""
    echo "  完成后回到后台「系统信息」页面点击「验证 Xcode」"
else
    echo "==========================================="
    echo -e "  ${RED}部分组件未通过检查，请查看上方错误信息${NC}"
    echo "==========================================="
    exit 1
fi
