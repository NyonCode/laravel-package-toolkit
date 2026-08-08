#!/usr/bin/env bash
#
# Regenerate the logo set from source — the mark, the lockups and the favicons.
# Idempotent: run it as often as you like, the output is deterministic.
#
#   ./art/render.sh
#
# Requires (system binaries, nothing from composer or npm):
#   python3   — builds the SVG geometry
#   resvg     — SVG -> PNG            https://github.com/linebender/resvg
#   usvg      — outlines <text>       (ships with resvg)
#
# Fonts are read from art/fonts, never from the system, so the output is
# identical on every machine.

set -euo pipefail

ART="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FONTS="$ART/fonts"

for bin in python3 resvg usvg; do
  command -v "$bin" >/dev/null || { echo "error: $bin not found on PATH" >&2; exit 1; }
done

RESVG=(resvg --skip-system-fonts --use-fonts-dir "$FONTS")
USVG=(usvg --skip-system-fonts --use-fonts-dir "$FONTS")

echo "==> geometry"
python3 "$ART/artboards/build.py"

echo "==> lockups: outlining text to paths"
mkdir -p "$ART/logo"
for f in lockup-horizontal lockup-horizontal-dark lockup-stacked lockup-stacked-dark; do
  "${USVG[@]}" "$ART/src/$f.svg" "$ART/logo/$f.svg"
  # usvg drops the viewBox and leaves only width/height, which stops the file
  # scaling when it is inlined and sized with CSS. Put it back.
  python3 - "$ART/logo/$f.svg" <<'PY'
import re, sys
p = sys.argv[1]
s = open(p).read()
if "viewBox" not in s:
    m = re.search(r'<svg width="([\d.]+)" height="([\d.]+)"', s)
    if m:
        s = s.replace(m.group(0), f'{m.group(0)} viewBox="0 0 {m.group(1)} {m.group(2)}"', 1)
        open(p, "w").write(s)
PY
  echo "$ART/logo/$f.svg"
done

echo "==> favicons"
mkdir -p "$ART/favicon"
# 32px: the bare mark, transparent, matching favicon.svg
"${RESVG[@]}" -w 32 -h 32 "$ART/favicon/favicon.svg" "$ART/favicon/favicon-32.png"
# 180 (apple-touch) and 512 (PWA/maskable): full-bleed opaque square with a
# generous safe margin. Platforms apply their own corner mask, so the artwork
# must have no radius and no transparency of its own.
python3 - "$ART" <<'PY'
import os, sys
art = sys.argv[1]
sys.path.insert(0, os.path.join(art, "artboards"))
import mark as M
os.makedirs(os.path.join(art, "favicon"), exist_ok=True)
with open(os.path.join(art, "favicon", ".tile.svg"), "w") as fh:
    fh.write(M.tile_svg(scale=0.70, radius_ratio=0))
PY
for s in 180 512; do
  "${RESVG[@]}" -w "$s" -h "$s" "$ART/favicon/.tile.svg" "$ART/favicon/favicon-$s.png"
done
rm -f "$ART/favicon/.tile.svg"
ls "$ART/favicon"/*.png

echo "==> dimensions"
for f in "$ART/favicon/favicon-32.png" "$ART/favicon/favicon-180.png" \
         "$ART/favicon/favicon-512.png"; do
  printf '%-44s %s\n' "${f#"$ART/"}" \
    "$(sips -g pixelWidth -g pixelHeight "$f" | awk '/pixel/{printf "%s ", $2}')"
done

echo "done."
