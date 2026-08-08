"""Lockups — mark plus wordmark.

Typography mirrors the docs-site masthead so the brand set and the site read as
one system:

    wordmark   Inter Display Bold, tracking -0.02em      (.brand__name)
    subline    Inter Medium, UPPERCASE, tracking +0.09em (.brand__meta)
    ratio      subline is 0.68x the wordmark, as on the site

The wordmark is "Package Toolkit" over "FOR LARAVEL" rather than "Laravel
Package Toolkit" on one line. That is the construction the site already uses,
and it keeps Laravel as a descriptor rather than the leading word of a
third-party brand.

These files are SOURCE: they contain live <text>. render.sh runs them through
usvg to produce the shipped, outlined art/logo/lockup-*.svg.
"""

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import mark as M
import ttf

HERE = os.path.dirname(os.path.abspath(__file__))
ART = os.path.dirname(HERE)
FONTS = os.path.join(ART, "fonts")

NAME = "Package Toolkit"
META = "FOR LARAVEL"

NAME_SIZE = 33.0
# The site sets the subline at 0.68x the brand name. That ratio is tuned for a
# 16px masthead; at lockup scale it makes the subline compete with the wordmark,
# so it is pulled back to 0.44x and given a little more tracking to compensate.
META_SIZE = NAME_SIZE * 0.44
NAME_TRACK = -0.02 * NAME_SIZE
META_TRACK = 0.12 * META_SIZE
SUBLINE_GAP = 8.0  # name baseline -> subline cap top

MARK_L, MARK_T, MARK_R, MARK_B = M.bbox()
GAP_H = 18.0   # mark to wordmark, horizontal lockup
GAP_V = 20.0   # mark to wordmark, stacked lockup

INK = {"light": ("#1a1a17", "#86857c"), "dark": ("#eceef5", "#7d7f97")}


def _fonts():
    return (
        ttf.Font(os.path.join(FONTS, "InterDisplay-Bold.ttf")),
        ttf.Font(os.path.join(FONTS, "Inter-Medium.ttf")),
    )


def measure():
    fb, fm = _fonts()
    cap = fb.cap_height * NAME_SIZE
    meta_cap = fm.cap_height * META_SIZE
    return {
        # trailing tracking is not ink, so drop one unit of it from the width
        "name_w": fb.text_width(NAME, NAME_SIZE, NAME_TRACK) - NAME_TRACK,
        "meta_w": fm.text_width(META, META_SIZE, META_TRACK) - META_TRACK,
        "cap": cap,
        "meta_cap": meta_cap,
        "baseline_gap": SUBLINE_GAP + meta_cap,
    }


def _text(x, y, s, size, family, weight, track, fill, anchor=None):
    a = f' text-anchor="{anchor}"' if anchor else ""
    return (
        f'<text x="{x:g}" y="{y:g}" font-family="{family}" font-weight="{weight}" '
        f'font-size="{size:g}" letter-spacing="{track:g}" fill="{fill}"{a}>{s}</text>'
    )


def horizontal_group(theme="light", prefix="lpt"):
    """The horizontal lockup as a placeable group: (inner, width, height).

    Origin is the top-left of the artwork, so callers can drop it into a
    composition with a plain translate/scale.
    """
    m = measure()
    name_ink, meta_ink = INK[theme]
    text_x = MARK_R + GAP_H
    block_h = m["cap"] + m["baseline_gap"]
    name_cap_top = (MARK_T + MARK_B) / 2 - block_h / 2
    name_base = name_cap_top + m["cap"]
    meta_base = name_base + m["baseline_gap"]

    w = text_x + max(m["name_w"], m["meta_w"]) - MARK_L
    h = MARK_B - MARK_T

    inner = (
        M.body(prefix)
        + _text(text_x, name_base, NAME, NAME_SIZE, "Inter Display", 700, NAME_TRACK, name_ink)
        + _text(text_x, meta_base, META, META_SIZE, "Inter Medium", 500, META_TRACK, meta_ink)
    )
    return f'<g transform="translate({-MARK_L:g} {-MARK_T:g})">{inner}</g>', w, h


def horizontal(theme="light"):
    inner, w, h = horizontal_group(theme)
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w:.2f} {h:.2f}" '
        f'width="{w:.2f}" height="{h:.2f}">{M.defs("lpt")}{inner}</svg>'
    )


def stacked(theme="light"):
    m = measure()
    name_ink, meta_ink = INK[theme]
    w = max(m["name_w"], MARK_R - MARK_L)
    cx = w / 2
    mark_x = cx - 32.0  # centre the 64u artboard
    name_base = MARK_B + GAP_V + m["cap"]
    meta_base = name_base + m["baseline_gap"]
    h = meta_base - MARK_T

    inner = (
        f'<g transform="translate({mark_x:.2f} 0)">{M.body("lpt")}</g>'
        + _text(cx, name_base, NAME, NAME_SIZE, "Inter Display", 700, NAME_TRACK, name_ink,
                anchor="middle")
        + _text(cx, meta_base, META, META_SIZE, "Inter Medium", 500, META_TRACK, meta_ink,
                anchor="middle")
    )
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w:.2f} {h:.2f}" '
        f'width="{w:.2f}" height="{h:.2f}">{M.defs("lpt")}'
        f'<g transform="translate(0 {-MARK_T:g})">{inner}</g></svg>'
    )


def main():
    src = os.path.join(ART, "src")
    os.makedirs(src, exist_ok=True)
    files = {
        "lockup-horizontal.svg": horizontal("light"),
        "lockup-horizontal-dark.svg": horizontal("dark"),
        "lockup-stacked.svg": stacked("light"),
        "lockup-stacked-dark.svg": stacked("dark"),
    }
    for name, txt in files.items():
        with open(os.path.join(src, name), "w") as fh:
            fh.write(txt)
        print(os.path.join(src, name))


if __name__ == "__main__":
    main()
