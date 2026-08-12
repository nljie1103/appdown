#!/bin/bash
set -euo pipefail

CONF=/etc/appdown-template-builder.conf
[ -r "$CONF" ] || { echo "Template Builder privileged helper is not bootstrapped" >&2; exit 78; }
# shellcheck disable=SC1090
source "$CONF"

: "${APPDOWN_PROJECT_ROOT:?missing APPDOWN_PROJECT_ROOT}"
: "${APPDOWN_TEMPLATE_BUILDER_IMAGE:=appdown/template-builder:2}"

CONTEXT="$APPDOWN_PROJECT_ROOT/builder/template-builder"
JOBS_ROOT="$APPDOWN_PROJECT_ROOT/data/template-jobs"

real_under_jobs() {
  local target real base
  target="$1"
  real="$(realpath -e "$target")"
  base="$(realpath -e "$JOBS_ROOT")"
  case "$real/" in
    "$base"/*/) printf '%s\n' "$real" ;;
    *) echo "job directory is outside AppDown data/template-jobs" >&2; exit 64 ;;
  esac
}

cmd="${1:-}"
case "$cmd" in
  status)
    docker info >/dev/null 2>&1 || exit 10
    docker image inspect "$APPDOWN_TEMPLATE_BUILDER_IMAGE" >/dev/null 2>&1 || exit 11
    printf '{"docker":true,"image":true,"image_name":"%s"}\n' "$APPDOWN_TEMPLATE_BUILDER_IMAGE"
    ;;
  install)
    docker info >/dev/null 2>&1 || { echo "Docker daemon unavailable" >&2; exit 10; }
    test -f "$CONTEXT/Dockerfile"
    docker build --pull -t "$APPDOWN_TEMPLATE_BUILDER_IMAGE" "$CONTEXT"
    docker image inspect "$APPDOWN_TEMPLATE_BUILDER_IMAGE" >/dev/null
    ;;
  uninstall)
    docker ps -a --filter "label=org.appdown.builder=template" -q | xargs -r docker rm -f >/dev/null
    if docker image inspect "$APPDOWN_TEMPLATE_BUILDER_IMAGE" >/dev/null 2>&1; then
      docker image rm -f "$APPDOWN_TEMPLATE_BUILDER_IMAGE"
    fi
    ;;
  build)
    job="${2:-}"
    test -n "$job" || { echo "missing job dir" >&2; exit 64; }
    job="$(real_under_jobs "$job")"
    test -f "$job/request.json"
    test -d "$job/input"
    test -d "$job/output"
    test -d "$job/secrets"

    uid="$(stat -c '%u' "$job")"
    gid="$(stat -c '%g' "$job")"

    docker image inspect "$APPDOWN_TEMPLATE_BUILDER_IMAGE" >/dev/null 2>&1 || {
      echo "Template Builder image is not installed" >&2
      exit 11
    }

    docker run --rm \
      --label org.appdown.builder=template \
      --network none \
      --read-only \
      --cap-drop ALL \
      --security-opt no-new-privileges \
      --pids-limit 256 \
      --memory "${APPDOWN_TEMPLATE_MEMORY:-2g}" \
      --cpus "${APPDOWN_TEMPLATE_CPUS:-2}" \
      --tmpfs /tmp:rw,noexec,nosuid,size=1g \
      -e HOME=/tmp/home \
      -v "$job/request.json:/workspace/request.json:ro" \
      -v "$job/input:/workspace/input:ro" \
      -v "$job/secrets:/run/secrets:ro" \
      -v "$job/output:/workspace/output:rw" \
      "$APPDOWN_TEMPLATE_BUILDER_IMAGE" /workspace/request.json

    chown -R "$uid:$gid" "$job/output"
    ;;
  *)
    echo "usage: appdown-template-builder-runner {status|install|uninstall|build JOB_DIR}" >&2
    exit 64
    ;;
esac
