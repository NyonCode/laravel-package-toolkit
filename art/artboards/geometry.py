"""
Laravel Package Toolkit — brand geometry.

Single source of truth for the mark. Everything is derived from one grid so the
stroke weights and corner radii stay consistent across the whole asset set.

GRID
----
The artboard is 64 x 64 units. Every published size is a scale of this grid.

    unit            u   = 1/64 of the artboard
    stroke          4u  (6.25%)   — same ratio as the docs-site icon (2 on 32)
    hairline seam   2u  (3.125%)  — the gap between assembled parts
    corner radius   3u  on the parts, 16u (25%) on the app-icon container
    cube half-width 21u

The cube is a true isometric projection: the hexagon half-height is
w * 2/sqrt(3), so the three faces are congruent 60/120 rhombi.
"""

import math

# --------------------------------------------------------------------- grid --

U = 64.0
STROKE = 4.0
SEAM = 2.0
R_PART = 3.0
R_CONTAINER = 16.0
W = 21.0  # cube half-width

CX = 32.0
CY = 32.0

# Optical centring: an isometric cube reads low when maths-centred, because the
# eye weights the two large lower faces. Lift the whole cube by 0.75u.
OPTICAL_LIFT = 0.75


def cube_points(w=W, cx=CX, cy=CY, lift=OPTICAL_LIFT):
    """Vertices of the isometric cube. C is the near (centre) vertex."""
    hh = w * 2.0 / math.sqrt(3.0)  # hexagon half-height
    cy = cy - lift
    return {
        "P1": (cx, cy - hh),            # far top
        "P2": (cx + w, cy - hh / 2.0),  # right top
        "P3": (cx + w, cy + hh / 2.0),  # right bottom
        "P4": (cx, cy + hh),            # near bottom
        "P5": (cx - w, cy + hh / 2.0),  # left bottom
        "P6": (cx - w, cy - hh / 2.0),  # left top
        "C": (cx, cy),                  # near top (where the three faces meet)
    }


def faces(w=W, cx=CX, cy=CY, lift=OPTICAL_LIFT):
    """The three visible faces of the cube, as point lists."""
    p = cube_points(w, cx, cy, lift)
    return {
        "top": [p["P6"], p["P1"], p["P2"], p["C"]],
        "right": [p["C"], p["P2"], p["P3"], p["P4"]],
        "left": [p["P5"], p["P6"], p["C"], p["P4"]],
    }


# ------------------------------------------------------------------- helpers --


def centroid(pts):
    return (sum(x for x, _ in pts) / len(pts), sum(y for _, y in pts) / len(pts))


def scale_about(pts, s, origin=None):
    ox, oy = origin if origin else centroid(pts)
    return [(ox + (x - ox) * s, oy + (y - oy) * s) for x, y in pts]


def inset_polygon(pts, d):
    """Offset a convex polygon inward.

    `d` is either a scalar (every edge) or a per-edge list, where d[i] applies
    to the edge running from pts[i] to pts[i+1]. Per-edge control is what lets a
    seam open only where two faces meet, leaving the outer silhouette intact.
    """
    n = len(pts)
    if not isinstance(d, (list, tuple)):
        d = [d] * n
    out = []
    for i in range(n):
        p_prev = pts[(i - 1) % n]
        p = pts[i]
        p_next = pts[(i + 1) % n]
        d_prev = d[(i - 1) % n]
        d_next = d[i % n]
        # inward normals of the two edges meeting at p
        n1 = _inward_normal(p_prev, p, pts)
        n2 = _inward_normal(p, p_next, pts)
        # offset both edges, intersect them
        a1 = (p_prev[0] + n1[0] * d_prev, p_prev[1] + n1[1] * d_prev)
        b1 = (p[0] + n1[0] * d_prev, p[1] + n1[1] * d_prev)
        a2 = (p[0] + n2[0] * d_next, p[1] + n2[1] * d_next)
        b2 = (p_next[0] + n2[0] * d_next, p_next[1] + n2[1] * d_next)
        out.append(_line_intersect(a1, b1, a2, b2) or p)
    return out


def _inward_normal(a, b, pts):
    dx, dy = b[0] - a[0], b[1] - a[1]
    ln = math.hypot(dx, dy)
    nx, ny = -dy / ln, dx / ln
    c = centroid(pts)
    mx, my = (a[0] + b[0]) / 2.0, (a[1] + b[1]) / 2.0
    if (c[0] - mx) * nx + (c[1] - my) * ny < 0:
        nx, ny = -nx, -ny
    return (nx, ny)


def _line_intersect(a1, b1, a2, b2):
    x1, y1 = a1
    x2, y2 = b1
    x3, y3 = a2
    x4, y4 = b2
    den = (x1 - x2) * (y3 - y4) - (y1 - y2) * (x3 - x4)
    if abs(den) < 1e-9:
        return None
    t = ((x1 - x3) * (y3 - y4) - (y1 - y3) * (x3 - x4)) / den
    return (x1 + t * (x2 - x1), y1 + t * (y2 - y1))


def translate(pts, dx, dy):
    return [(x + dx, y + dy) for x, y in pts]


def fmt(v):
    return f"{round(v, 2):g}"


# --------------------------------------------------------------- path output --


def rounded_path(pts, r, close=True):
    """Rounded-corner path through a polygon, using true circular arcs."""
    n = len(pts)
    segs = []
    starts = []
    ends = []
    for i in range(n):
        p_prev = pts[(i - 1) % n]
        p = pts[i]
        p_next = pts[(i + 1) % n]
        v1 = _unit(p, p_prev)
        v2 = _unit(p, p_next)
        # interior half-angle between the two edge directions
        dot = max(-1.0, min(1.0, v1[0] * v2[0] + v1[1] * v2[1]))
        theta = math.acos(dot)
        if theta < 1e-6 or abs(theta - math.pi) < 1e-6:
            starts.append(p)
            ends.append(p)
            continue
        d = r / math.tan(theta / 2.0)
        d = min(d, _dist(p, p_prev) / 2.0, _dist(p, p_next) / 2.0)
        rr = d * math.tan(theta / 2.0)
        starts.append((p[0] + v1[0] * d, p[1] + v1[1] * d))
        ends.append((p[0] + v2[0] * d, p[1] + v2[1] * d))
        # Sweep direction from the turn at this corner, so the arc always
        # bulges towards the vertex (convex) regardless of winding order.
        cross = v1[0] * v2[1] - v1[1] * v2[0]
        segs.append((i, (rr, 1 if cross < 0 else 0)))

    radii = dict(segs)
    out = [f"M{fmt(ends[0][0])} {fmt(ends[0][1])}"]
    for i in range(1, n + 1):
        j = i % n
        out.append(f"L{fmt(starts[j][0])} {fmt(starts[j][1])}")
        if j in radii:
            rr, sweep = radii[j]
            out.append(f"A{fmt(rr)} {fmt(rr)} 0 0 {sweep} {fmt(ends[j][0])} {fmt(ends[j][1])}")
    if close:
        out.append("Z")
    return "".join(out)


def _unit(a, b):
    dx, dy = b[0] - a[0], b[1] - a[1]
    ln = math.hypot(dx, dy)
    return (dx / ln, dy / ln)


def _dist(a, b):
    return math.hypot(b[0] - a[0], b[1] - a[1])


def _is_ccw(pts):
    """0 if clockwise in SVG coords (y down), 1 if counter-clockwise."""
    s = 0.0
    for i in range(len(pts)):
        x1, y1 = pts[i]
        x2, y2 = pts[(i + 1) % len(pts)]
        s += (x2 - x1) * (y2 + y1)
    return 0 if s > 0 else 1


def poly(pts, close=True):
    d = "M" + "L".join(f"{fmt(x)} {fmt(y)}" for x, y in pts)
    return d + ("Z" if close else "")
