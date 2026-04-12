#!/bin/bash
#=====================================================
# APK 构建环境一键部署脚本
# 适用于 Ubuntu 20.04 / 22.04 / 24.04
# 用法: sudo bash tools/setup-android-env.sh
#=====================================================

set -e

# ========== 配置 ==========
JAVA_PKG="openjdk-17-jdk"
ANDROID_HOME="/opt/android-sdk"
CMDLINE_TOOLS_URL="https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip"
# 如果无法访问 Google，使用以下镜像（腾讯云）
CMDLINE_TOOLS_MIRROR="https://mirrors.cloud.tencent.com/AndroidSDK/commandlinetools-linux-11076708_latest.zip"
BUILD_TOOLS_VERSION="34.0.0"
PLATFORM_VERSION="android-34"

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

if [ ! -f /etc/os-release ] || ! grep -qi 'ubuntu\|debian' /etc/os-release; then
    warn "此脚本针对 Ubuntu/Debian 编写，其他发行版可能需要调整"
fi

echo ""
echo "========================================="
echo "  APK 构建环境一键部署"
echo "========================================="
echo ""
echo "将安装以下组件："
echo "  - OpenJDK 17"
echo "  - Android SDK 命令行工具"
echo "  - Build Tools $BUILD_TOOLS_VERSION"
echo "  - Platform $PLATFORM_VERSION"
echo ""

# ========== Step 1: 安装 JDK ==========
echo "--- Step 1/4: 安装 OpenJDK 17 ---"

# 兜底检测：任意路径的 Java 17（不仅限 apt 安装）
JAVA17_FOUND=false
if java -version 2>&1 | grep -q '"17'; then
    JAVA17_FOUND=true
    log "检测到 Java 17 已安装（通过 java -version）"
elif command -v java > /dev/null 2>&1; then
    JAVA_REAL=$(readlink -f $(which java) 2>/dev/null)
    if [ -n "$JAVA_REAL" ]; then
        JAVA_VER=$("$JAVA_REAL" -version 2>&1 | head -1)
        if echo "$JAVA_VER" | grep -q '"17'; then
            JAVA17_FOUND=true
            log "检测到 Java 17 已安装（路径: $JAVA_REAL）"
        fi
    fi
fi

if [ "$JAVA17_FOUND" = true ]; then
    log "OpenJDK 17 已安装，跳过"
else
    log "正在安装 $JAVA_PKG ..."
    apt-get update -qq
    apt-get install -y -qq $JAVA_PKG > /dev/null 2>&1
    if java -version 2>&1 | grep -q '17'; then
        log "OpenJDK 17 安装成功"
    else
        error "OpenJDK 17 安装失败"
        exit 1
    fi
fi

JAVA_HOME=$(dirname $(dirname $(readlink -f $(which java))))
log "JAVA_HOME = $JAVA_HOME"

# ========== Step 2: 安装 Android SDK 命令行工具 ==========
echo ""
echo "--- Step 2/4: 安装 Android SDK 命令行工具 ---"

SDKMANAGER="$ANDROID_HOME/cmdline-tools/latest/bin/sdkmanager"

# 兜底检测：检测任意路径的 sdkmanager
EXISTING_SDK=""
if [ -f "$SDKMANAGER" ]; then
    EXISTING_SDK="$ANDROID_HOME"
    log "Android SDK 命令行工具已存在于 $ANDROID_HOME，跳过下载"
elif command -v sdkmanager > /dev/null 2>&1; then
    # sdkmanager 在 PATH 中，向上3级找到 ANDROID_HOME
    SDK_REAL=$(readlink -f $(which sdkmanager) 2>/dev/null)
    if [ -n "$SDK_REAL" ]; then
        DETECTED_HOME=$(dirname $(dirname $(dirname $(dirname "$SDK_REAL"))))
        if [ -d "$DETECTED_HOME/cmdline-tools" ]; then
            EXISTING_SDK="$DETECTED_HOME"
            ANDROID_HOME="$DETECTED_HOME"
            SDKMANAGER="$SDK_REAL"
            log "检测到 Android SDK 已安装（路径: $ANDROID_HOME）"
        fi
    fi
fi

if [ -n "$EXISTING_SDK" ]; then
    log "使用已有的 SDK: $ANDROID_HOME"
else
    log "创建目录 $ANDROID_HOME/cmdline-tools ..."
    mkdir -p "$ANDROID_HOME/cmdline-tools"
    cd "$ANDROID_HOME/cmdline-tools"

    TMPZIP="/tmp/android-cmdline-tools.zip"

    # 尝试从 Google 下载
    log "从 Google 下载 cmdline-tools ..."
    if wget -q --timeout=30 -O "$TMPZIP" "$CMDLINE_TOOLS_URL" 2>/dev/null; then
        log "从 Google 下载成功"
    else
        warn "Google 下载失败，尝试腾讯云镜像 ..."
        if wget -q --timeout=30 -O "$TMPZIP" "$CMDLINE_TOOLS_MIRROR" 2>/dev/null; then
            log "从腾讯云镜像下载成功"
        else
            # 最后尝试用 curl
            warn "wget 失败，尝试 curl ..."
            if curl -sL --connect-timeout 30 -o "$TMPZIP" "$CMDLINE_TOOLS_URL" 2>/dev/null; then
                log "curl 下载成功"
            elif curl -sL --connect-timeout 30 -o "$TMPZIP" "$CMDLINE_TOOLS_MIRROR" 2>/dev/null; then
                log "curl 从镜像下载成功"
            else
                error "所有下载方式均失败，请手动下载："
                echo "  $CMDLINE_TOOLS_URL"
                echo "  解压到 $ANDROID_HOME/cmdline-tools/latest"
                exit 1
            fi
        fi
    fi

    # 检查下载文件有效性
    if [ ! -s "$TMPZIP" ]; then
        error "下载的文件为空"
        rm -f "$TMPZIP"
        exit 1
    fi

    log "解压 cmdline-tools ..."
    unzip -q -o "$TMPZIP" -d "$ANDROID_HOME/cmdline-tools/"

    # Google 打包结构: cmdline-tools/cmdline-tools/... -> 需要移动到 latest
    if [ -d "$ANDROID_HOME/cmdline-tools/cmdline-tools" ]; then
        rm -rf "$ANDROID_HOME/cmdline-tools/latest"
        mv "$ANDROID_HOME/cmdline-tools/cmdline-tools" "$ANDROID_HOME/cmdline-tools/latest"
    fi

    rm -f "$TMPZIP"

    if [ -f "$SDKMANAGER" ]; then
        log "Android SDK 命令行工具安装成功"
    else
        error "安装后未找到 sdkmanager，请检查目录结构"
        ls -la "$ANDROID_HOME/cmdline-tools/"
        exit 1
    fi
fi # 结束 EXISTING_SDK 检测块

# ========== Step 3: 接受许可 & 安装组件 ==========
echo ""
echo "--- Step 3/4: 安装 Build Tools 和 Platform ---"

log "接受 SDK 许可协议 ..."
yes 2>/dev/null | "$SDKMANAGER" --licenses > /dev/null 2>&1 || true

log "安装 build-tools;$BUILD_TOOLS_VERSION ..."
"$SDKMANAGER" "build-tools;$BUILD_TOOLS_VERSION" > /dev/null 2>&1
log "安装 platforms;$PLATFORM_VERSION ..."
"$SDKMANAGER" "platforms;$PLATFORM_VERSION" > /dev/null 2>&1

log "SDK 组件安装完成"

# ========== Step 4: 验证环境 ==========
echo ""
echo "--- Step 4/4: 验证环境 ---"

PASS=true

# 检查 Java
if java -version 2>&1 | grep -q '17'; then
    log "Java 17 .............. OK"
else
    error "Java 17 .............. FAILED"
    PASS=false
fi

# 检查 sdkmanager
if [ -f "$SDKMANAGER" ]; then
    log "sdkmanager ........... OK"
else
    error "sdkmanager ........... FAILED"
    PASS=false
fi

# 检查 build-tools
if [ -d "$ANDROID_HOME/build-tools/$BUILD_TOOLS_VERSION" ]; then
    log "build-tools $BUILD_TOOLS_VERSION ... OK"
else
    error "build-tools $BUILD_TOOLS_VERSION ... FAILED"
    PASS=false
fi

# 检查 platform
if [ -d "$ANDROID_HOME/platforms/$PLATFORM_VERSION" ]; then
    log "platform $PLATFORM_VERSION ...... OK"
else
    error "platform $PLATFORM_VERSION ...... FAILED"
    PASS=false
fi

# 检查 keytool
if command -v keytool > /dev/null 2>&1; then
    log "keytool .............. OK"
else
    error "keytool .............. FAILED"
    PASS=false
fi

echo ""
if [ "$PASS" = true ]; then
    echo "========================================="
    echo -e "  ${GREEN}环境部署完成！所有检查通过${NC}"
    echo "========================================="
    echo ""
    echo "  JAVA_HOME    = $JAVA_HOME"
    echo "  ANDROID_HOME = $ANDROID_HOME"
    echo ""
    echo "  现在可以使用后台「生成应用」功能了。"
else
    echo "========================================="
    echo -e "  ${RED}部分组件未通过检查，请查看上方错误信息${NC}"
    echo "========================================="
    exit 1
fi
