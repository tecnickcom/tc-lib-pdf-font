"""Build ivstest.ttf, the TrueType test font with a cmap format 14 subtable.

Run with fontTools installed: python3 make_ivs_font.py
The glyphs are rectangles; only the glyph indices, advances and cmap matter.
"""

import os

from fontTools.fontBuilder import FontBuilder
from fontTools.pens.ttGlyphPen import TTGlyphPen

GLYPHS = [
    # name, advance width
    (".notdef", 500),
    ("space", 250),
    ("A", 600),
    ("A.vs1", 700),
    ("A.vs2", 800),
    ("B", 610),
    ("uni908A", 1000),
    ("uni908A.vs1", 900),
]

CMAP = {
    0x0020: "space",
    0x0041: "A",
    0x0042: "B",
    0x908A: "uni908A",
}

# (base, selector, glyph name), None selects the default glyph of the base
UVS = [
    (0x0041, 0xFE00, None),
    (0x0041, 0xFE01, "A.vs1"),
    (0x0042, 0xFE01, "A.vs1"),
    (0x0041, 0xFE02, "A.vs2"),
    (0x908A, 0xE0100, None),
    (0x908A, 0xE0101, "uni908A.vs1"),
]


def box(width):
    pen = TTGlyphPen(None)
    if width > 250:
        pen.moveTo((50, 0))
        pen.lineTo((50, 700))
        pen.lineTo((width - 50, 700))
        pen.lineTo((width - 50, 0))
        pen.closePath()
    return pen.glyph()


def main():
    names = [name for name, _ in GLYPHS]
    fb = FontBuilder(1000, isTTF=True)
    fb.setupGlyphOrder(names)
    fb.setupCharacterMap(CMAP, uvs=UVS)
    fb.setupGlyf({name: box(width) for name, width in GLYPHS})
    fb.setupHorizontalMetrics({name: (width, 50) for name, width in GLYPHS})
    fb.setupHorizontalHeader(ascent=800, descent=-200)
    fb.setupNameTable({"familyName": "IVS Test", "styleName": "Regular", "psName": "IVSTest-Regular"})
    fb.setupOS2(sTypoAscender=800, sTypoDescender=-200, usWinAscent=800, usWinDescent=200, fsType=0)
    fb.setupPost()
    fb.save(os.path.join(os.path.dirname(os.path.abspath(__file__)), "ivstest.ttf"))


if __name__ == "__main__":
    main()
