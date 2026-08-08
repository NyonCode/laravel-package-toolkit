"""Isometric construction kit.

Everything is modelled in 3D unit space and projected, so a carton, its flaps
and its seams all share one coordinate system and cannot drift out of alignment.

    x  runs right-and-down on screen
    y  runs left-and-down on screen
    z  runs up

    screen_x = (x - y) * cos(30)
    screen_y = (x + y) * sin(30) - z
"""

import math

COS30 = math.sqrt(3) / 2.0
SIN30 = 0.5


def project(p):
    x, y, z = p
    return ((x - y) * COS30, (x + y) * SIN30 - z)


def face(pts3):
    return [project(p) for p in pts3]


def bbox(polys):
    xs = [p[0] for poly in polys for p in poly]
    ys = [p[1] for poly in polys for p in poly]
    return min(xs), min(ys), max(xs), max(ys)


def fit(polys, target=54.0, cx=32.0, cy=32.0, lift=0.0):
    """Uniformly scale a set of projected polygons to fit `target`, then centre.

    Centring is on the drawn bounding box, then nudged up by `lift` units:
    a box shape reads low when its bounding box is centred mathematically.
    """
    x0, y0, x1, y1 = bbox(polys)
    w, h = x1 - x0, y1 - y0
    s = target / max(w, h)
    ox = cx - (x0 + x1) / 2.0 * s
    oy = cy - (y0 + y1) / 2.0 * s - lift
    return [[(p[0] * s + ox, p[1] * s + oy) for p in poly] for poly in polys], s


def carton(h=1.0):
    """The six corners of a carton body, keyed by position.

    Top face  A(back) B(right) C(front) D(left);  bottom face primed.
    """
    return {
        "A": (0, 0, h), "B": (1, 0, h), "C": (1, 1, h), "D": (0, 1, h),
        "B_": (1, 0, 0), "C_": (1, 1, 0), "D_": (0, 1, 0),
    }


def body_faces(h=1.0):
    v = carton(h)
    return {
        "top": [v["A"], v["B"], v["C"], v["D"]],
        "right": [v["B"], v["C"], v["C_"], v["B_"]],
        "left": [v["D"], v["C"], v["C_"], v["D_"]],
    }


def flap(edge, length, angle_deg, h=1.0):
    """A flap hinged on a top edge, folded outward by `angle_deg` from level.

    edge: 'back-right' hinges on A-B and splays up-right,
          'back-left'  hinges on A-D and splays up-left.
    """
    a = math.radians(angle_deg)
    dy = length * math.cos(a)
    dz = length * math.sin(a)
    if edge == "back-right":
        return [(0, 0, h), (1, 0, h), (1, -dy, h + dz), (0, -dy, h + dz)]
    if edge == "back-left":
        return [(0, 0, h), (0, 1, h), (-dy, 1, h + dz), (-dy, 0, h + dz)]
    raise ValueError(edge)
