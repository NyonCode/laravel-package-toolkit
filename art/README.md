# Laravel Package Toolkit — brand assets

Everything here is generated from source by [`render.sh`](./render.sh). Nothing is
hand-drawn in a binary format, there are no external references, and no asset depends
on a font being installed.

---

## The mark

An **isometric shipping carton**, drawn as solid faces separated by negative-space seams,
with a wider seam splitting the lid where the flaps meet.

The flap seam is the single idea beyond "a cube". It is what makes the silhouette read as
*package* rather than as a generic 3D block — and it does it with no gradient, no stroke
and no shadow, which is why the mark survives being flattened to one colour or shrunk to
16px.

The concept the mark carries: **a package, assembled from parts, described once.**

### Construction

One grid governs the whole set. The artboard is **64 × 64 units** (1u = 1/64 of the mark's
width); every published size is a scale of it.

| Value | Units | As a ratio |
| --- | --- | --- |
| Artboard | 64 × 64 | — |
| Drawn size | 54 | 84% of the artboard |
| Box height | 0.82 | carton proportion, deliberately not a cube |
| Hairline seam (faces meet) | 1.5u | 2.3% |
| Flap seam (lid halves meet) | 2.5u | hairline + 1u |
| Corner radius (every part) | 1.2u | 1.9% |
| Optical lift | 1.0u | see below |

**Two seam weights, on purpose.** At 512px the full construction is legible — lid, two side
faces, flap join. At 16px the hairlines fall below one pixel and fade out, leaving a solid,
unambiguous box with a single seam across the lid. The mark therefore carries different
amounts of detail at different sizes instead of the same amount at all of them.

**Optical lift.** The mark is centred on its drawn bounding box and then raised 1u. An
isometric box centred by arithmetic reads low, because the eye weights the two large lower
faces.

The isometric projection is true (`screen_x = (x−y)·cos30`, `screen_y = (x+y)·sin30 − z`),
so all three faces are congruent 60/120 rhombi. Geometry lives in
[`artboards/iso.py`](./artboards/iso.py) and [`artboards/geometry.py`](./artboards/geometry.py);
the mark itself is [`artboards/mark.py`](./artboards/mark.py).

---

## Palette

Taken from the documentation site's token block (`site/assets/docs.css`) so the brand set
and the site are one system.

### Brand

| Role | Hex | Notes |
| --- | --- | --- |
| Accent — light theme | `#dc2626` | UI accent on white. Clears 4.5:1, which the mark's own lit face does not |
| Accent — dark theme | `#f87171` | UI accent on dark — the mark's lit face |
| Accent hover | `#b91c1c` (light) · `#fca5a5` (dark) | |
| Gradient | `#ef4444` → `#dc2626` | 120° |
| Solid fill | `#dc2626` | Buttons; the same in both themes, because a filled surface's contrast is with its own label, not the page |

> **On red, and on Laravel.** Laravel's own brand red is `#F53003`, and this is a red mark on
> a package *for* Laravel. The colour alone will read as official to anyone who does not look
> twice, so the identity carries no second Laravel cue anywhere: no brushstroke, no Laravel
> "L", nothing borrowed from their marks. The form does the whole job of saying *third party*.
> Keep it that way.

### Mark face gradients

The mark does **not** use one gradient plus opacity. Each face carries its own gradient,
because opacity-based shading inverts on light backgrounds — the "shadow" faces go paler
instead of darker, which would force separate artwork per background. Explicit per-face
gradients make one full-colour mark correct everywhere.

| Face | From | To |
| --- | --- | --- |
| Lid | `#f87171` | `#ef4444` |
| Right | `#dc2626` | `#c11d1d` |
| Left | `#991b1b` | `#7f1616` |

Defined once, in `FACE_GRADIENTS` in [`artboards/mark.py`](./artboards/mark.py). Everything
else — favicons, lockups, all three cards — derives from there, so recolouring the brand is
one edit plus `./art/render.sh`.

Gradient vector, in artboard units: `(4, 6) → (60, 58)`, `userSpaceOnUse`.

### Surfaces and ink

| Role | Hex |
| --- | --- |
| Dark background | `#0c0c11` |
| Dark surface | `#131320` |
| Dark border | `#24243a` |
| Light background | `#ffffff` |
| Light ink | `#1a1a17` |
| Muted ink | `#5f5e57` (light) · `#a2a4bb` (dark) |
| Faint ink | `#86857c` (light) · `#7d7f97` (dark) |
| Code surface | `#292d3e` (light theme) · `#1c1f2e` (dark theme) |
| Tile / touch-icon ground | `#16161f` |

---

## Typography

| Use | Face | Licence |
| --- | --- | --- |
| Wordmark, headings | **Inter Display** Bold, tracking −0.02em | SIL OFL 1.1 |
| Subline, body | **Inter** Medium / Regular | SIL OFL 1.1 |
| Code, repo slug | **JetBrains Mono** Regular | SIL OFL 1.1 |

Inter was chosen because the site renders in `ui-sans-serif, system-ui` — Inter is the
closest openly-licensed relative to that, so the assets and the live site look like the
same brand. It is also legally clean to outline and redistribute, which the macOS system
faces (SF Pro, Helvetica Neue) are not.

Subset copies live in [`fonts/`](./fonts) with their licences. They are subset to Latin
only, about 39KB each, and are the *only* fonts used — `render.sh` passes
`--skip-system-fonts`, so output is byte-identical on any machine.

**The shipped SVG lockups contain no text.** `usvg` converts `<text>` to paths, so
`logo/lockup-*.svg` render identically everywhere with no font dependency. The editable
sources with live `<text>` are in [`src/`](./src) — **that is what you edit.**

The wordmark is "Package Toolkit" over "FOR LARAVEL", matching the site masthead. Laravel
stays a descriptor rather than the leading word of a third-party brand. The site sets the
subline at 0.68× the name; that ratio is tuned for a 16px masthead and overpowers the
wordmark at lockup scale, so the lockups use 0.44× with slightly more tracking.

---

## Files

### Logo — `logo/`

| File | Use |
| --- | --- |
| `mark.svg` | The symbol, full colour, transparent. Works on light **and** dark. |
| `mark-mono.svg` | One colour via `currentColor`. Inherits the parent's `color`. |
| `lockup-horizontal.svg` | Mark + wordmark, for **light** backgrounds |
| `lockup-horizontal-dark.svg` | Same, ink inverted, for **dark** backgrounds |
| `lockup-stacked.svg` | Mark above wordmark, **light** backgrounds |
| `lockup-stacked-dark.svg` | Same, **dark** backgrounds |

There is no `mark-light.svg` / `mark-dark.svg`. The per-face gradients make one mark
correct on both grounds, so separate files would be duplicates. The *lockups* do need
per-background files, because the wordmark ink has to change.

> `mark-mono.svg` renders **black** when opened on its own — `currentColor` has nothing to
> inherit outside a document. Set `color` on it (`<svg style="color:#f87171">`, or a CSS
> rule on the parent) and it takes that colour.

### Favicon — `favicon/`

| File | Size | Form |
| --- | --- | --- |
| `favicon.svg` | vector | Bare mark, transparent |
| `favicon-32.png` | 32×32 | Bare mark, transparent |
| `favicon-180.png` | 180×180 | Full-bleed opaque square (apple-touch) |
| `favicon-512.png` | 512×512 | Full-bleed opaque square (PWA / maskable) |

The favicon is the bare mark, not a tile: at 16px a rounded tile spends about a fifth of
every edge on its own corner radius, and the mark left inside is meaningfully smaller than
one drawn edge to edge.

The touch icons are the opposite case — **full bleed, no radius, no transparency.** iOS and
Android apply their own corner mask; artwork with its own rounded corners shows black
wedges outside that radius. They use the `#16161f` ground with a generous safe margin
(mark at 70%).

> **This directory is the logo and nothing else.** The marketing compositions that once
> lived here — a 1280×640 GitHub social preview, a 1200×630 Open Graph card and a wide
> README banner — were removed along with the `compose.py` that built them. If they are
> ever wanted back, they are a new artboard against this same mark, not a restoration.
>
> One loose end from that removal: `site/assets/og-image.png` is still in place and still
> referenced by the documentation site's `og:image`. It works, but it no longer has a
> source here, so it cannot be regenerated. Either leave it as a static file or drop the
> `og:image` tags from `site/templates/chrome.mjs`.

---

## Using the assets

### Clear space

Leave **25% of the mark's height** clear on every side, free of type, rules and other
marks. For the lockups, measure the mark inside the lockup and apply the same rule.

`logo/mark.svg` already carries about 4u of padding inside its 64u artboard. That is part
of the artboard, not the clear space — add the clear space outside it.

### Minimum sizes

Measured, not guessed — below these the subline turns to a grey smudge.

| Asset | Minimum |
| --- | --- |
| `mark.svg` | **16px** wide |
| `lockup-horizontal*.svg` | **200px** wide |
| `lockup-stacked*.svg` | **150px** wide |

If you need the brand smaller than the lockup minimum, use the mark on its own.

### Which variant on which background

| Background | Mark | Lockup |
| --- | --- | --- |
| White / light `#ffffff`–`#f7f7f6` | `mark.svg` | `lockup-*.svg` |
| Dark `#0c0c11`–`#131320` | `mark.svg` | `lockup-*-dark.svg` |
| Brand violet, photo, or busy | `mark-mono.svg` set to white | mono lockup — set `color`, or use the PNG |
| Print, single ink, engraving | `mark-mono.svg` | — |

---

## Do

- Use `mark.svg` unmodified on any background — it is built to work on both.
- Use `mark-mono.svg` when you only have one colour, and set `color` on it.
- Scale proportionally, from the SVGs.
- Keep the clear space.
- Use the `-dark` lockups on dark grounds so the wordmark stays legible.

## Don't

- **Don't** recolour the faces individually or replace the gradient with a flat fill —
  use `mark-mono.svg` if you need one colour.
- **Don't** rotate, shear, or change the isometric angle. The projection is exact.
- **Don't** add drop shadows, glows, bevels or outlines to the mark.
- **Don't** re-space, re-typeset or substitute the wordmark. It is outlined for a reason.
- **Don't** stretch a lockup to fit — the aspect is fixed.
- **Don't** put a light-ink lockup on a dark ground, or the reverse. It disappears.
- **Don't** place the mark on a violet of similar value; the left face will vanish.
- **Don't** use the rounded tile as a favicon or the bare mark as a touch icon.
- **Don't** imitate Laravel's own branding — no `#F53003`, no brushstroke L. This is a
  third-party package and the identity is deliberately its own.
- **Don't** edit `logo/*.svg` or the PNGs by hand; they are generated.

---

## Re-rendering

```bash
./art/render.sh
```

One command regenerates every PNG and every outlined SVG. It is idempotent and
deterministic — fonts come from `art/fonts`, never from the system.

Requires three system binaries (nothing from Composer or npm):

| Tool | Purpose |
| --- | --- |
| `python3` | builds the SVG geometry |
| [`resvg`](https://github.com/linebender/resvg) | SVG → PNG at exact pixel sizes |
| `usvg` | outlines `<text>` to paths (ships with resvg) |

The script prints the final pixel dimensions of every PNG so a mismatch is obvious.

### Source vs generated

| Path | Status |
| --- | --- |
| `artboards/mark.py` | **Source.** The mark's geometry and the face gradients. |
| `artboards/lockup.py` | **Source.** Wordmark typesetting and lockup layout. |
| `artboards/{geometry,iso,ttf}.py` | **Source.** Rounded paths, isometric projection, font metrics. |
| `artboards/build.py` | **Source.** Writes the SVGs the render script then rasterises. |
| `src/*.svg` | **Generated, editable.** Lockups with live `<text>` — edit these for typographic tweaks. |
| `logo/*.svg`, `favicon/*` | **Generated. Do not hand-edit.** |
| `fonts/*` | Vendored subsets + licences. |

To change the mark itself, edit `artboards/mark.py` and re-run. To change the wordmark's
typography, edit `artboards/lockup.py` (or `src/*.svg` for a one-off) and re-run.

---

## What was explored, and what was rejected

The contact sheets are gone; this is the part worth keeping. Every concept below was judged
**flat, in one colour, at 16/32/96px** before any gradient was applied — which is the only
test that separates a mark from a picture.

**Rejected on the way to the carton**

| Concept | Why it died |
| --- | --- |
| Cube with the top face lifted clear | Mud below 32px; the lid read as detached rather than opening |
| Container with publishing bars | Read as a lowercase letterform or a document, not a package |
| Three stacked isometric plates | Reads "layers/database", and it is one of the most over-used developer-tool marks there is |
| Wireframe cube with a solid top | Wireframe plus solid fill is mud below 32px — this was the original placeholder's actual problem |
| Tile with the cube knocked out | The outer square fights the cube in the negative space and reads closer to a stamp. Survives only as the touch-icon treatment |
| Open carton with splayed flaps | Beautiful at 96px, confetti at 16px |

**A bug worth remembering.** The first pass had corner-rounding arcs sweeping the wrong way,
biting a concave notch out of every vertex. It read as damage, not as a motif, and it was
mistaken for a style choice before it was diagnosed. Fixed in `geometry.rounded_path` by
deriving the sweep flag from the turn direction at each corner rather than from the polygon
winding.

**Why the seam has two weights.** With a single weight everywhere, the lid detached at large
sizes and floated above the body as two slabs. A fully attached lid fixed that but lost the
lid's edge in mono. The hybrid — narrow structural seam, wide flap seam — is what gives full
construction at 512px and a solid silhouette at 16px.

**A later round tried to replace the carton and failed.** Six concepts built on the
library's mechanism rather than its category — a brace holding the set it expands into, the
bare `->` operator, a fan, a chain of arrows, a prism, a scored block. Two survived to a
final sheet and neither was taken. Findings worth not rediscovering:

- A brace with bars beside it reads as **€** at 16px. So does a vertical with three bars —
  it reads as **E**. Anything in that family becomes a glyph before it becomes a mark.
- Deepening a brace's nib to firm it up at small sizes turns it into a **left-pointing
  arrowhead**, reversing the reading direction.
- A bare `->` is by far the most legible object of the set and the most PHP-native idea
  available, but without its explanation it reads as "next" or "play".
- Scoring a solid block to fix its silhouette lands exactly on the stock UI-layout glyph —
  trading one stock icon for another.

**And a warning about the palette.** In violet the carton read "developer tool". In red it
reads *courier* — parcel-delivery territory. The colour did not leave the mark where it
found it. If the mark is ever revisited, that is the reason to start.

---

## Licensing

The artwork is part of this repository and carries the repository's licence.

The bundled fonts are third-party and keep their own:

- **Inter** — © The Inter Project Authors, SIL Open Font License 1.1 —
  [`fonts/LICENSE-Inter.txt`](./fonts/LICENSE-Inter.txt)
- **JetBrains Mono** — © The JetBrains Mono Project Authors, SIL Open Font License 1.1 —
  [`fonts/LICENSE-JetBrainsMono.txt`](./fonts/LICENSE-JetBrainsMono.txt)

The OFL permits both redistribution of the font files and the outlining of glyphs into
artwork, which is what the lockups do.

Laravel is a trademark of Taylor Otwell. This is an unaffiliated third-party package; the
identity deliberately shares none of Laravel's brand assets.
