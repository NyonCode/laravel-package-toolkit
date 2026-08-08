"""The mark — canonical definition. Everything else in the set imports this.

THE MARK
--------
An isometric shipping carton, built from three solid faces separated by
constant-width negative seams, with a fourth seam splitting the top face where
the flaps meet. The flap seam is the one idea beyond "cube": it is what makes
the silhouette read as *package* rather than as a generic 3D block, and it does
so with no gradient, no stroke and no shadow.

TWO SEAM WEIGHTS, ON PURPOSE
----------------------------
The flap seam is wide (2.5u) and the structural seams where the faces meet are
hairlines (1.5u). This is what makes the mark hold across the whole size range:

  at 512px  the full construction is legible — lid, two side faces, flap join
  at 16px   the hairlines fall below a pixel and fade out, leaving a solid,
            unambiguous box silhouette with a single seam across the lid

An earlier version used one seam weight everywhere. At large sizes the lid
detached and floated above the body as two separate slabs; the hairline is what
keeps it attached.

LOCKED PARAMETERS (see art/README.md for the grid)
    artboard        64 x 64 units
    box height      0.82 (carton proportion, not a cube)
    hairline seam   1.5u  — where faces meet
    flap seam       2.5u  — where the lid halves meet (hairline + 1u)
    corner radius   1.2u on every part
    drawn size      54u, leaving 5u of built-in margin
    optical lift    1.0u — a box reads low when its bbox is centred by maths

COLOUR
------
Each face carries its own gradient rather than one gradient plus opacity.
Opacity-based shading inverts on light backgrounds (the shadow faces go paler
instead of darker), which would force separate light/dark artwork. Explicit
per-face gradients make a single full-colour mark correct on any background.
"""

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import geometry as G
import iso

# ------------------------------------------------------------------- locked --

H = 0.82
SEAM_HAIRLINE = 1.5
SEAM_FLAP = 2.5
RADIUS = 1.2
TARGET = 54.0
LIFT = 1.0

# Face gradients, lit from the top left. Chroma is held as the faces darken so
# the box stays red instead of drifting brown: each face keeps its
# saturation and only loses lightness, which is what makes three flat fills read
# as one lit object.
FACE_GRADIENTS = {
    "top": ("#f87171", "#ef4444"),
    "right": ("#dc2626", "#c11d1d"),
    "left": ("#991b1b", "#7f1616"),
}
GRAD_VECTOR = 'x1="4" y1="6" x2="60" y2="58" gradientUnits="userSpaceOnUse"'


HAIR, FLAP = 1, 2  # seam-flag codes


def parts(h=H):
    """The four drawn parts of the carton, in paint order."""
    v = iso.carton(h)
    A, B, C, D = v["A"], v["B"], v["C"], v["D"]
    B_, C_, D_ = v["B_"], v["C_"], v["D_"]
    M0, M1 = (0, 0.5, h), (1, 0.5, h)
    return [
        # lid, split across the middle by the flap seam
        ("top", [A, B, M1, M0], [0, HAIR, FLAP, 0]),
        ("top", [M0, M1, C, D], [FLAP, HAIR, HAIR, 0]),
        ("right", [B, C, C_, B_], [HAIR, HAIR, 0, 0]),
        ("left", [D, C, C_, D_], [HAIR, HAIR, 0, 0]),
    ]


def geometry(h=H, hairline=SEAM_HAIRLINE, flap=SEAM_FLAP, radius=RADIUS,
             target=TARGET, lift=LIFT):
    """Final 2D paths, keyed by face role, in the 64u artboard."""
    ps = parts(h)
    fitted, _ = iso.fit([iso.face(p[1]) for p in ps], target=target, lift=lift)
    widths = {0: 0.0, HAIR: hairline / 2, FLAP: flap / 2}
    out = []
    for (role, _, flags), pts in zip(ps, fitted):
        d = [widths[f] for f in flags]
        pts = G.inset_polygon(pts, d) if any(flags) else pts
        out.append((role, G.rounded_path(pts, radius)))
    return out


def bbox(h=H, target=TARGET, lift=LIFT):
    """True drawn bounds of the mark inside the 64u artboard (x0, y0, x1, y1).

    Measured rather than assumed: the carton is taller than it is wide, so the
    fit lands on the height and the artwork does not touch the side margins.
    """
    fitted, _ = iso.fit([iso.face(p[1]) for p in parts(h)], target=target, lift=lift)
    xs = [p[0] for poly in fitted for p in poly]
    ys = [p[1] for poly in fitted for p in poly]
    return min(xs), min(ys), max(xs), max(ys)


def defs(prefix="lpt"):
    grads = []
    for role, (a, b) in FACE_GRADIENTS.items():
        grads.append(
            f'<linearGradient id="{prefix}-{role}" {GRAD_VECTOR}>'
            f'<stop stop-color="{a}"/><stop offset="1" stop-color="{b}"/></linearGradient>'
        )
    return f"<defs>{''.join(grads)}</defs>"


def body(prefix="lpt", paint=None, **kw):
    """Mark body. `paint` forces a single colour (mono); otherwise gradients."""
    out = []
    for role, d in geometry(**kw):
        fill = paint if paint else f"url(#{prefix}-{role})"
        out.append(f'<path d="{d}" fill="{fill}"/>')
    return "".join(out)


def svg(size=None, prefix="lpt", paint=None, transform=None, **kw):
    dim = f' width="{size}" height="{size}"' if size else ""
    inner = body(prefix, paint, **kw)
    if transform:
        inner = f'<g transform="{transform}">{inner}</g>'
    head = "" if paint else defs(prefix)
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"{dim}>'
        f"{head}{inner}</svg>"
    )


# ----------------------------------------------------------------- the tile --

TILE_BG = "#16161f"
TILE_RADIUS_RATIO = 0.25  # 16u on 64 — matches the docs-site favicon


def tile_svg(size=None, bg=TILE_BG, prefix="lpt", scale=0.82, radius_ratio=TILE_RADIUS_RATIO):
    """App-icon tile: opaque square, mark centred optically on it.

    `radius_ratio=0` gives a full-bleed square, which is what the touch icons
    need. A rounded tile has transparent corners; iOS composites those onto
    black and then applies its own squircle mask, so any corner radius of ours
    that is tighter than Apple's shows up as black wedges. Platforms round the
    icon themselves — the artwork must be opaque edge to edge.
    """
    dim = f' width="{size}" height="{size}"' if size else ""
    r = 64 * radius_ratio
    sq = (
        G.rounded_path([(0, 0), (64, 0), (64, 64), (0, 64)], r)
        if r > 0 else "M0 0H64V64H0Z"
    )
    off = 32 - 32 * scale
    inner = f'<g transform="translate({G.fmt(off)} {G.fmt(off)}) scale({scale})">{body(prefix)}</g>'
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"{dim}>'
        f'{defs(prefix)}<path d="{sq}" fill="{bg}"/>{inner}</svg>'
    )
