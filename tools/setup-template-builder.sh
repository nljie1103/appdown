#!/bin/bash
set -euo pipefail

IMAGE="${APPDOWN_TEMPLATE_BUILDER_IMAGE:-appdown/template-builder:2}"
WEB_USER="${APPDOWN_WEB_USER:-www-data}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
RUNNER_SRC="$PROJECT_DIR/tools/template-builder-runner.sh"
RUNNER_DST="/usr/local/libexec/appdown-template-builder-runner"
CONF="/etc/appdown-template-builder.conf"
SUDOERS="/etc/sudoers.d/appdown-template-builder"

ok(){ printf '[✓] %s\n' "$*"; }
die(){ printf '[✗] %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "首次 Bootstrap 需要 root：sudo bash tools/setup-template-builder.sh"
id "$WEB_USER" >/dev/null 2>&1 || die "Web 用户不存在: $WEB_USER（可用 APPDOWN_WEB_USER=... 指定）"

if ! command -v docker >/dev/null 2>&1; then
  ok "安装 Docker CE（共享运行时；不会安装 Android SDK/JDK/Xcode 到宿主机）"
  apt-get update -qq
  apt-get install -y -qq ca-certificates curl gnupg >/dev/null
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  . /etc/os-release
  ARCH_DPKG="$(dpkg --print-architecture)"
  echo "deb [arch=$ARCH_DPKG signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $VERSION_CODENAME stable" >/etc/apt/sources.list.d/docker.list
  apt-get update -qq
  apt-get install -y -qq docker-ce docker-ce-cli containerd.io sudo >/dev/null
fi

systemctl enable --now docker >/dev/null 2>&1 || true
docker info >/dev/null 2>&1 || die "Docker daemon 未运行"

install -d -m 0755 /usr/local/libexec
install -o root -g root -m 0755 "$RUNNER_SRC" "$RUNNER_DST"

cat >"$CONF" <<EOF
APPDOWN_PROJECT_ROOT=$(printf '%q' "$PROJECT_DIR")
APPDOWN_TEMPLATE_BUILDER_IMAGE=$(printf '%q' "$IMAGE")
EOF
chown root:root "$CONF"
chmod 0644 "$CONF"

cat >"$SUDOERS" <<EOF
# AppDown Builder 2.0: web process may only invoke the immutable root-owned runner.
$WEB_USER ALL=(root) NOPASSWD: $RUNNER_DST
EOF
chmod 0440 "$SUDOERS"
visudo -cf "$SUDOERS" >/dev/null || { rm -f "$SUDOERS"; die "sudoers 校验失败"; }

install -d -o "$WEB_USER" -g "$(id -gn "$WEB_USER")" -m 0750 "$PROJECT_DIR/data/template-jobs"

ok "构建本机架构镜像: $IMAGE ($(uname -m))"
"$RUNNER_DST" install

ok "Bootstrap 完成"
echo "Web 后台现在只能通过固定 runner 执行 Template Builder；PHP 未获得任意 docker 命令权限。"
