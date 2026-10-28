#!/usr/bin/env bash
set -euo pipefail

# Genera una imagen independiente por secador y, si está configurado,
# la entrega al servicio de WhatsApp.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

BASE_URL="${REPORT_BASE_URL:-http://192.168.1.105/reportes_progel}"
CAPTURE_PUBLIC_PATH="${SECADORES_CAPTURE_PUBLIC_PATH:-/assets/generated/secadores-whatsapp}"
OUTPUT_DIR="${SECADORES_CAPTURE_DIR:-${PROJECT_ROOT}${CAPTURE_PUBLIC_PATH}}"
CHROME_BIN="${CHROME_BIN:-google-chrome}"
CAPTURE_WIDTH="${SECADORES_CAPTURE_WIDTH:-1920}"
CAPTURE_HEIGHT="${SECADORES_CAPTURE_HEIGHT:-1080}"
CAPTURE_ONLY="${SECADORES_CAPTURE_ONLY:-0}"
WHATSAPP_URL="${SERVICE_WHATSAPP_BASE_URL:-http://localhost:3000}"
WHATSAPP_ENDPOINT="${SERVICE_WHATSAPP_IMAGE_ENDPOINT:-/send-image}"
WHATSAPP_GROUP_ID="${SERVICE_WHATSAPP_GROUP_ID:-120363421423401133@g.us}"
WHATSAPP_API_KEY="${SERVICE_WHATSAPP_API_KEY:-WhatsappSErVices1313}"
IMAGE_REFERENCE="${SERVICE_WHATSAPP_IMAGE_REFERENCE:-path}"
PUBLIC_URL="${SECADORES_CAPTURE_PUBLIC_URL:-}"
WHATSAPP_CONTAINER="${SERVICE_WHATSAPP_DOCKER_CONTAINER:-whatsapp-service}"
WHATSAPP_CONTAINER_DIR="${SERVICE_WHATSAPP_CONTAINER_CAPTURE_DIR:-/tmp/secadores-whatsapp}"

mkdir -p "$OUTPUT_DIR"

json_string() {
  if command -v python3 >/dev/null 2>&1; then
    JSON_VALUE="$1" python3 -c 'import json, os; print(json.dumps(os.environ["JSON_VALUE"], ensure_ascii=False), end="")'
    return
  fi

  if command -v node >/dev/null 2>&1; then
    JSON_VALUE="$1" node -e 'process.stdout.write(JSON.stringify(process.env.JSON_VALUE || ""))'
    return
  fi

  if command -v jq >/dev/null 2>&1; then
    printf '%s' "$1" | jq -Rs .
    return
  fi

  printf '"%s"' "$(printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g')"
}

capture_dryer() {
  local tunnel="$1"
  local output="$OUTPUT_DIR/${tunnel}.png"
  local profile
  profile="$(mktemp -d "/tmp/secadores-chrome-${tunnel}.XXXXXX")"

  "$CHROME_BIN" \
    --headless \
    --disable-gpu \
    --no-sandbox \
    --disable-dev-shm-usage \
    --disable-crash-reporter \
    --disable-breakpad \
    --disable-background-mode \
    --no-first-run \
    --hide-scrollbars \
    --run-all-compositor-stages-before-draw \
    --virtual-time-budget=5000 \
    --force-device-scale-factor=1 \
    "--user-data-dir=${profile}" \
    "--window-size=${CAPTURE_WIDTH},${CAPTURE_HEIGHT}" \
    "--screenshot=${output}" \
    "${BASE_URL%/}/reports/secadores/?capture=1&secador=${tunnel}" \
    >/dev/null 2>&1

  rm -rf "$profile"

  if [[ ! -s "$output" ]]; then
    echo "No se pudo generar la captura de ${tunnel}." >&2
    return 1
  fi

  printf '%s\n' "$output"
}

send_image() {
  local image_path="$1"
  local caption="$2"
  local image_ref="$image_path"

  if [[ "$IMAGE_REFERENCE" == "url" ]]; then
    local public_base="${PUBLIC_URL:-${BASE_URL%/}${CAPTURE_PUBLIC_PATH}}"
    image_ref="${public_base%/}/$(basename "$image_path")?v=$(date +%s)"
  elif [[ -n "$WHATSAPP_CONTAINER" ]]; then
    docker exec "$WHATSAPP_CONTAINER" mkdir -p "$WHATSAPP_CONTAINER_DIR" >/dev/null
    image_ref="${WHATSAPP_CONTAINER_DIR%/}/$(basename "$image_path")"
    docker cp "$image_path" "${WHATSAPP_CONTAINER}:${image_ref}" >/dev/null
  fi

  local payload endpoint response_file status
  payload="{\"groupId\":$(json_string "$WHATSAPP_GROUP_ID"),\"imageUrl\":$(json_string "$image_ref"),\"caption\":$(json_string "$caption")}" 
  endpoint="${WHATSAPP_URL%/}/${WHATSAPP_ENDPOINT#/}"
  response_file="$(mktemp /tmp/secadores-whatsapp-response.XXXXXX)"

  local headers=(-H "Content-Type: application/json")
  if [[ -n "$WHATSAPP_API_KEY" ]]; then
    headers+=(-H "x-api-key: ${WHATSAPP_API_KEY}")
  fi

  status="$(curl -sS -o "$response_file" -w '%{http_code}' -X POST "$endpoint" "${headers[@]}" --data "$payload")"
  rm -f "$response_file"

  if [[ ! "$status" =~ ^2 ]]; then
    echo "El servicio de WhatsApp respondió HTTP ${status}." >&2
    return 1
  fi

  echo "Imagen enviada: ${caption}"
}

main() {
  local captured_at tunnel label image
  captured_at="$(date '+%d/%m/%Y %H:%M')"

  for tunnel in tunel_1 tunel_2; do
    label="Secador ${tunnel#tunel_}"
    image="$(capture_dryer "$tunnel")"
    echo "Captura lista: ${image}"

    if [[ "$CAPTURE_ONLY" == "1" ]]; then
      continue
    fi
    if [[ -z "$WHATSAPP_GROUP_ID" ]]; then
      echo "Captura no enviada: configura SERVICE_WHATSAPP_GROUP_ID o usa SECADORES_CAPTURE_ONLY=1." >&2
      continue
    fi

    send_image "$image" "${label} | ${captured_at}"
  done
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
  main "$@"
fi
