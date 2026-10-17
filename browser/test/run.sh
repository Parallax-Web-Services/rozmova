#!/usr/bin/env bash
#
# Runs the browser-layer tests in headless Chromium.
#
# The DOM layer cannot be tested without a DOM, and a stub DOM would not catch
# the things that actually break: TreeWalker filters, contenteditable, ruby
# construction, MutationObserver timing. So these run in a real browser against
# the real module graph, served over HTTP because ES module imports do not work
# from file://.
#
# Usage: browser/test/run.sh [path-to-chrome]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PORT="${ROZMOVA_TEST_PORT:-8799}"
WORK="$(mktemp -d)"

CHROME="${1:-}"
if [ -z "$CHROME" ]; then
  for candidate in \
    "$(ls -d /opt/pw-browsers/chromium*/chrome-linux/chrome 2>/dev/null | head -1)" \
    "$(command -v google-chrome || true)" \
    "$(command -v chromium-browser || true)" \
    "$(command -v chromium || true)"; do
    if [ -n "$candidate" ] && [ -x "$candidate" ]; then CHROME="$candidate"; break; fi
  done
fi

if [ -z "$CHROME" ]; then
  echo "No Chromium found. Pass a path: browser/test/run.sh /path/to/chrome" >&2
  exit 1
fi

python3 -m http.server "$PORT" --bind 127.0.0.1 --directory "$ROOT" >/dev/null 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true; rm -rf "$WORK"' EXIT

for _ in $(seq 1 80); do
  if (exec 3<>"/dev/tcp/127.0.0.1/$PORT") 2>/dev/null; then exec 3>&-; break; fi
done

failed=0

for page in dom reader; do
  "$CHROME" --headless --disable-gpu --no-sandbox --disable-http-cache \
    --user-data-dir="$WORK/$page" --virtual-time-budget=9000 \
    --dump-dom "http://127.0.0.1:$PORT/browser/test/$page.html" 2>/dev/null > "$WORK/$page.html"

  summary=$(python3 - "$WORK/$page.html" <<'PY'
import html, pathlib, re, sys
s = pathlib.Path(sys.argv[1]).read_text()
m = re.search(r'<pre id="results">(.*?)</pre>', s, re.S)
print(html.unescape(m.group(1)).strip() if m else "NO RESULTS — the page script threw before reporting")
PY
)

  echo "browser/$page"
  echo "$(echo "$summary" | sed 's/^/  /')"
  echo

  if echo "$summary" | grep -q "FAIL\|NO RESULTS"; then failed=1; fi
done

exit $failed
