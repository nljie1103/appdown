#!/bin/bash
set -euo pipefail

DOCKER_OSX_IMAGE="${DOCKER_OSX_IMAGE:-sickcodes/docker-osx:auto}"
CONTAINER_NAME="${CONTAINER_NAME:-ysapp-ios-builder}"
SSH_PORT="${SSH_PORT:-50922}"
DOCKER_DATA_ROOT="${DOCKER_DATA_ROOT:-}"
DOCKER_MIRROR="${DOCKER_MIRROR:-}"
IOS_SSH_KEY="${IOS_SSH_KEY:-}"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
log(){ echo -e "${GREEN}[✓]${NC} $1"; }
warn(){ echo -e "${YELLOW}[!]${NC} $1"; }
error(){ echo -e "${RED}[✗]${NC} $1"; }

[ "$(id -u)" -eq 0 ] || { error "请使用 sudo 运行此脚本"; exit 1; }
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DEFAULT_SSH_KEY="$PROJECT_DIR/data/ios_builder_ed25519"
[ -n "$IOS_SSH_KEY" ] || IOS_SSH_KEY="$DEFAULT_SSH_KEY"

ARCH="$(uname -m | tr '[:upper:]' '[:lower:]')"
if [ "$ARCH" != "x86_64" ] && [ "$ARCH" != "amd64" ]; then
  error "Docker-OSX 需要 x86_64 KVM 宿主机，当前架构: $ARCH"
  echo "ARM/aarch64 服务器不能运行这条 Docker-OSX 构建路线。"
  exit 1
fi
[ -e /dev/kvm ] || { error "KVM 不可用（缺少 /dev/kvm）"; exit 1; }

if ! command -v docker >/dev/null 2>&1; then
  log "安装 Docker CE ..."
  apt-get update -qq
  apt-get install -y -qq ca-certificates curl gnupg >/dev/null
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  . /etc/os-release
  ARCH_DPKG="$(dpkg --print-architecture)"
  echo "deb [arch=$ARCH_DPKG signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $VERSION_CODENAME stable" >/etc/apt/sources.list.d/docker.list
  apt-get update -qq
  apt-get install -y -qq docker-ce docker-ce-cli containerd.io >/dev/null
fi
systemctl enable --now docker >/dev/null 2>&1 || true
docker info >/dev/null 2>&1 || { error "Docker daemon 未运行"; exit 1; }
log "Docker: $(docker --version | head -1)"

if [ -n "$DOCKER_DATA_ROOT" ] || [ -n "$DOCKER_MIRROR" ]; then
  python3 - "$DOCKER_DATA_ROOT" "$DOCKER_MIRROR" <<'PY'
import json,sys,os
p='/etc/docker/daemon.json'; cfg={}
if os.path.exists(p):
    try: cfg=json.load(open(p))
    except Exception: cfg={}
if sys.argv[1]: os.makedirs(sys.argv[1],exist_ok=True); cfg['data-root']=sys.argv[1]
if sys.argv[2]: cfg['registry-mirrors']=[x.strip() for x in sys.argv[2].split(',') if x.strip()]
os.makedirs('/etc/docker',exist_ok=True)
json.dump(cfg,open(p,'w'),indent=2)
PY
  systemctl restart docker
fi

if [[ "$DOCKER_OSX_IMAGE" == *":auto" ]]; then
  apt-get update -qq
  apt-get install -y -qq openssh-client sshpass >/dev/null
  warn "使用 Docker-OSX :auto 预制 Catalina CLI 镜像；如需现代 Xcode，请改用已经完成 macOS 安装并启用 SSH 的较新自定义镜像。"
else
  apt-get update -qq
  apt-get install -y -qq openssh-client >/dev/null
  warn "非 :auto Docker-OSX 镜像通常需要先完成 macOS 初始安装并启用 SSH；AppDown 只有在 SSH 真正可达后才会标记 Phase 1 成功。"
fi

if ! docker image inspect "$DOCKER_OSX_IMAGE" >/dev/null 2>&1; then
  log "拉取 $DOCKER_OSX_IMAGE ..."
  docker pull "$DOCKER_OSX_IMAGE"
fi

if docker ps -a --format '{{.Names}}' | grep -qx "$CONTAINER_NAME"; then
  docker start "$CONTAINER_NAME" >/dev/null 2>&1 || true
  log "复用已有容器 $CONTAINER_NAME"
else
  log "创建 macOS 容器 $CONTAINER_NAME"
  docker run -d --name "$CONTAINER_NAME" --device /dev/kvm \
    -p "127.0.0.1:${SSH_PORT}:10022" \
    -e RAM=8 -e NOPICKER=true -e GENERATE_UNIQUE=true \
    "$DOCKER_OSX_IMAGE" >/dev/null
fi

docker ps --format '{{.Names}}' | grep -qx "$CONTAINER_NAME" || { error "macOS 容器未运行"; exit 1; }

ssh_key_probe(){
  [ -f "$IOS_SSH_KEY" ] || return 1
  ssh -i "$IOS_SSH_KEY" -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=5 -o BatchMode=yes -p "$SSH_PORT" user@localhost 'echo ok' 2>/dev/null | grep -qx ok
}
ssh_auto_probe(){
  command -v sshpass >/dev/null 2>&1 || return 1
  sshpass -p alpine ssh -o StrictHostKeyChecking=accept-new -o ConnectTimeout=5 -o PreferredAuthentications=password -o PubkeyAuthentication=no -p "$SSH_PORT" user@localhost 'echo ok' 2>/dev/null | grep -qx ok
}

log "等待 macOS SSH 真正就绪 ..."
SSH_OK=false
for _ in $(seq 1 60); do
  if ssh_key_probe; then SSH_OK=true; break; fi
  if [[ "$DOCKER_OSX_IMAGE" == *":auto" ]] && ssh_auto_probe; then
    if [ ! -f "$IOS_SSH_KEY" ]; then
      mkdir -p "$(dirname "$IOS_SSH_KEY")"
      ssh-keygen -q -t ed25519 -N '' -f "$IOS_SSH_KEY"
      chmod 600 "$IOS_SSH_KEY"
      chmod 644 "${IOS_SSH_KEY}.pub"
      if [ "$IOS_SSH_KEY" = "$DEFAULT_SSH_KEY" ] && [ -d "$PROJECT_DIR/data" ]; then
        DATA_OWNER="$(stat -c '%u:%g' "$PROJECT_DIR/data" 2>/dev/null || true)"
        [ -z "$DATA_OWNER" ] || chown "$DATA_OWNER" "$IOS_SSH_KEY" "${IOS_SSH_KEY}.pub"
      fi
    fi
    PUBKEY="$(cat "${IOS_SSH_KEY}.pub")"
    sshpass -p alpine ssh -o StrictHostKeyChecking=accept-new -o ConnectTimeout=5 -p "$SSH_PORT" user@localhost \
      "umask 077; mkdir -p ~/.ssh; touch ~/.ssh/authorized_keys; grep -qxF '$PUBKEY' ~/.ssh/authorized_keys || printf '%s\\n' '$PUBKEY' >> ~/.ssh/authorized_keys; chmod 700 ~/.ssh; chmod 600 ~/.ssh/authorized_keys" >/dev/null 2>&1 || true
    if ssh_key_probe; then SSH_OK=true; break; fi
  fi
  sleep 10
done

if [ "$SSH_OK" != true ]; then
  error "macOS SSH 在 10 分钟内未就绪，Phase 1 判定失败。"
  echo "容器已创建/启动，但 AppDown 不会再把‘容器存在’误报为‘iOS 环境完成’。"
  echo "请完成 macOS 初始安装、开启 Remote Login，并确保 user 可使用 AppDown 专用 SSH key 后重试。"
  exit 1
fi

MACOS_VER="$(ssh -i "$IOS_SSH_KEY" -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=5 -o BatchMode=yes -p "$SSH_PORT" user@localhost 'sw_vers -productVersion' 2>/dev/null | head -1 || true)"
log "SSH ................. OK"
log "macOS ............... ${MACOS_VER:-已连接}"
log "Phase 1 安装完成：Docker、KVM、容器、SSH 全部真实通过"
