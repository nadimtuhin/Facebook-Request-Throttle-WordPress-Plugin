#!/usr/bin/env bash
# HTTP smoke: drive the REAL plugin over HTTP (php -S + curl).
# Asserts: FB UA hit1=200, hit2=429 (+Retry-After), case-variant UA shares the bucket (429),
# meta-externalagent is an independent bucket (200), browser UA untouched (200).
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

export NT_SMOKE_STORE="$(mktemp)"
PORT="${NT_SMOKE_PORT:-8937}"

php -S "127.0.0.1:$PORT" backlog/smoke-harness.php >/dev/null 2>&1 &
SRV=$!
trap 'kill "$SRV" 2>/dev/null || true; rm -f "$NT_SMOKE_STORE"' EXIT

for _ in $(seq 1 25); do
  curl -s -o /dev/null "http://127.0.0.1:$PORT/warmup/" && break
  sleep 0.2
done

code() { curl -s -o /dev/null -w '%{http_code}' -A "$1" "http://127.0.0.1:$PORT/some-page/"; }

c1=$(code 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)')
c2=$(code 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)')
retry=$(curl -s -o /dev/null -D - -A 'FacebookExternalHit/1.1' "http://127.0.0.1:$PORT/x/" | tr -d '\r' | grep -i '^Retry-After:' || true)
c3=$(code 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)')
c4=$(code 'Mozilla/5.0 (Macintosh) Safari/605.1.15')

echo "  fb-hit1=$c1 fb-hit2=$c2 case-variant='$retry' meta-bucket=$c3 browser=$c4"

[ "$c1" = 200 ] && [ "$c2" = 429 ] && [ -n "$retry" ] && [ "$c3" = 200 ] && [ "$c4" = 200 ]
