"""Minimal TrueType metrics reader — enough to measure a wordmark.

Only advance widths are needed, so this parses head / hhea / hmtx / cmap and
nothing else. Kerning is ignored: for the short Latin strings in this brand set
the difference is well under half a unit on a 64u grid, and keeping this
dependency-free is what lets art/render.sh run anywhere with no Python packages
installed.
"""

import struct


class Font:
    def __init__(self, path):
        with open(path, "rb") as fh:
            self.data = fh.read()
        self.tables = {}
        num_tables = struct.unpack(">H", self.data[4:6])[0]
        for i in range(num_tables):
            off = 12 + i * 16
            tag = self.data[off:off + 4].decode("latin-1")
            start, length = struct.unpack(">II", self.data[off + 8:off + 16])
            self.tables[tag] = (start, length)

        head = self.tables["head"][0]
        self.units_per_em = struct.unpack(">H", self.data[head + 18:head + 20])[0]

        hhea = self.tables["hhea"][0]
        self.num_h_metrics = struct.unpack(">H", self.data[hhea + 34:hhea + 36])[0]

        self._cmap = self._parse_cmap()

    def _parse_cmap(self):
        start = self.tables["cmap"][0]
        n = struct.unpack(">H", self.data[start + 2:start + 4])[0]
        best = None
        for i in range(n):
            off = start + 4 + i * 8
            pid, eid, sub = struct.unpack(">HHI", self.data[off:off + 8])
            fmt = struct.unpack(">H", self.data[start + sub:start + sub + 2])[0]
            if fmt in (4, 12):
                # prefer a Unicode BMP/full table
                if best is None or (pid, eid) in ((3, 1), (3, 10), (0, 3), (0, 4)):
                    best = (fmt, start + sub)
        if best is None:
            raise ValueError("no usable cmap subtable")
        fmt, off = best
        return self._cmap4(off) if fmt == 4 else self._cmap12(off)

    def _cmap4(self, off):
        seg_x2 = struct.unpack(">H", self.data[off + 6:off + 8])[0]
        seg = seg_x2 // 2
        ends = struct.unpack(">%dH" % seg, self.data[off + 14:off + 14 + seg_x2])
        s = off + 16 + seg_x2
        starts = struct.unpack(">%dH" % seg, self.data[s:s + seg_x2])
        d = s + seg_x2
        deltas = struct.unpack(">%dh" % seg, self.data[d:d + seg_x2])
        r = d + seg_x2
        ranges = struct.unpack(">%dH" % seg, self.data[r:r + seg_x2])
        out = {}
        for i in range(seg):
            for c in range(starts[i], min(ends[i], 0xFFFF) + 1):
                if ranges[i] == 0:
                    g = (c + deltas[i]) & 0xFFFF
                else:
                    gi = r + i * 2 + ranges[i] + (c - starts[i]) * 2
                    if gi + 2 > len(self.data):
                        continue
                    g = struct.unpack(">H", self.data[gi:gi + 2])[0]
                    if g:
                        g = (g + deltas[i]) & 0xFFFF
                if g:
                    out[c] = g
        return out

    def _cmap12(self, off):
        n = struct.unpack(">I", self.data[off + 12:off + 16])[0]
        out = {}
        for i in range(n):
            g = off + 16 + i * 12
            s, e, gi = struct.unpack(">III", self.data[g:g + 12])
            for c in range(s, e + 1):
                out[c] = gi + (c - s)
        return out

    def advance(self, gid):
        start = self.tables["hmtx"][0]
        if gid < self.num_h_metrics:
            return struct.unpack(">H", self.data[start + gid * 4:start + gid * 4 + 2])[0]
        last = start + (self.num_h_metrics - 1) * 4
        return struct.unpack(">H", self.data[last:last + 2])[0]

    def text_width(self, text, size, letter_spacing=0.0):
        """Advance width of `text` at `size` user units, plus tracking."""
        total = 0
        for ch in text:
            gid = self._cmap.get(ord(ch), 0)
            total += self.advance(gid)
        w = total / self.units_per_em * size
        return w + letter_spacing * len(text)

    @property
    def cap_height(self):
        """Inter's cap height, read from OS/2 when present."""
        if "OS/2" in self.tables:
            off = self.tables["OS/2"][0]
            ver = struct.unpack(">H", self.data[off:off + 2])[0]
            if ver >= 2:
                return struct.unpack(">h", self.data[off + 88:off + 90])[0] / self.units_per_em
        return 0.727
