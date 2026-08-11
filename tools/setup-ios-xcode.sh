#!/bin/bash
# AppDown iOS Builder Phase 2: interactive Xcode installer.
set -euo pipefail

SSH_PORT="${SSH_PORT:-50922}"
CONTAINER_NAME="${CONTAINER_NAME:-ysapp-ios-builder}"
XCODE_VERSION="${XCODE_VERSION:-}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
IOS_SSH_KEY="${IOS_SSH_KEY:-$PROJECT_DIR/data/ios_builder_ed25519}"
KNOWN_HOSTS="${KNOWN_HOSTS:-$PROJECT_DIR/data/ios_known_hosts}"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
log(){ echo -e "${GREEN}[✓]${NC} $1"; }
warn(){ echo -e "${YELLOW}[!]${NC} $1"; }
error(){ echo -e "${RED}[✗]${NC} $1"; }

[[ "$SSH_PORT" =~ ^[0-9]+$ ]] && [ "$SSH_PORT" -ge 1 ] && [ "$SSH_PORT" -le 65535 ] || { error "SSH_PORT 必须为 1-65535"; exit 1; }
command -v docker >/dev/null 2>&1 || { error "未安装 Docker"; exit 1; }
docker ps --format '{{.Names}}' | grep -qx "$CONTAINER_NAME" || { error "macOS 容器 $CONTAINER_NAME 未运行"; exit 1; }
[ -r "$IOS_SSH_KEY" ] || { error "AppDown iOS SSH 私钥不存在或不可读: $IOS_SSH_KEY"; echo "请先通过后台安装 iOS 环境，或重新执行 tools/setup-ios-env.sh 完成 SSH key bootstrap。"; exit 1; }
mkdir -p "$(dirname "$KNOWN_HOSTS")"
touch "$KNOWN_HOSTS"
chmod 600 "$KNOWN_HOSTS" || true

SSH_OPTS=(-i "$IOS_SSH_KEY" -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile="$KNOWN_HOSTS" -o ConnectTimeout=10 -p "$SSH_PORT")
ssh_cmd(){ ssh "${SSH_OPTS[@]}" user@localhost "$@"; }

ssh_cmd 'echo ok' 2>/dev/null | grep -qx ok || { error "macOS SSH 未就绪，不能安装 Xcode"; exit 1; }
MACOS_VERSION="$(ssh_cmd 'sw_vers -productVersion' 2>/dev/null | head -1)"
log "SSH 连接成功 · macOS ${MACOS_VERSION:-未知}"

if [ -z "$XCODE_VERSION" ] && [ -n "$MACOS_VERSION" ] && command -v sort >/dev/null 2>&1; then
  FIRST="$(printf '%s\n%s\n' "$MACOS_VERSION" '11.0' | sort -V | head -1)"
  if [ "$FIRST" = "$MACOS_VERSION" ] && [ "$MACOS_VERSION" != '11.0' ]; then
    XCODE_VERSION='12.4'
    warn "检测到 macOS $MACOS_VERSION，自动选择 Xcode 12.4；可用 XCODE_VERSION=... 覆盖。"
  fi
fi

REMOTE_BOOTSTRAP='export PATH="/usr/local/bin:/opt/homebrew/bin:$PATH"; if command -v brew >/dev/null 2>&1; then brew --version | head -1; else echo __NO_BREW__; fi'
BREW_STATE="$(ssh_cmd "$REMOTE_BOOTSTRAP" 2>/dev/null || true)"
if echo "$BREW_STATE" | grep -q '__NO_BREW__'; then
  log "Homebrew 未安装，正在安装 ..."
  ssh -t "${SSH_OPTS[@]}" user@localhost '/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"'
fi

XCODES_OK="$(ssh_cmd 'export PATH="/usr/local/bin:/opt/homebrew/bin:$PATH"; command -v xcodes >/dev/null 2>&1 && echo yes || echo no' 2>/dev/null)"
if [ "$XCODES_OK" != 'yes' ]; then
  log "安装 xcodes CLI ..."
  ssh_cmd 'export PATH="/usr/local/bin:/opt/homebrew/bin:$PATH"; brew install xcodesorg/made/xcodes'
fi

if [ -n "$XCODE_VERSION" ]; then
  TARGET_DESC="Xcode $XCODE_VERSION"
  REMOTE_INSTALL="export PATH=\"/usr/local/bin:/opt/homebrew/bin:\$PATH\"; xcodes install '$XCODE_VERSION'"
else
  TARGET_DESC='最新版兼容 Xcode'
  REMOTE_INSTALL='export PATH="/usr/local/bin:/opt/homebrew/bin:$PATH"; xcodes install --latest'
fi

echo ""
echo "即将安装：$TARGET_DESC"
echo "安装过程会交互式要求 Apple ID / 两步验证码，下载体积较大。"
read -r -p "按 Enter 继续..."

set +e
ssh -t "${SSH_OPTS[@]}" user@localhost "$REMOTE_INSTALL; echo; sudo xcodebuild -license accept; echo; xcodebuild -version"
RESULT=$?
set -e

if [ "$RESULT" -ne 0 ]; then
  error "Xcode 安装或最终 xcodebuild 验证失败"
  exit "$RESULT"
fi

VERIFY="$(ssh_cmd 'xcodebuild -version' 2>/dev/null || true)"
echo "$VERIFY"
echo "$VERIFY" | grep -q '^Xcode ' || { error "未检测到可用 xcodebuild"; exit 1; }
log "Xcode 已通过 xcodebuild 实际验证"
