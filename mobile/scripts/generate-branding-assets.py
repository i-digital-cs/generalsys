#!/usr/bin/env python3
"""Generate Android launcher + splash assets from official GNRL branding.

Preserves logo aspect ratio and colors. Places the mark on a black canvas
(matching the official presentation). Does not stretch the source artwork.
"""

from __future__ import annotations

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
BRANDING = ROOT / "branding"
RES = ROOT / "android" / "app" / "src" / "main" / "res"
BG = (0, 0, 0, 255)

# Density → launcher / adaptive-foreground pixel sizes (Android convention).
LAUNCHER = {
    "mipmap-mdpi": (48, 108),
    "mipmap-hdpi": (72, 162),
    "mipmap-xhdpi": (96, 216),
    "mipmap-xxhdpi": (144, 324),
    "mipmap-xxxhdpi": (192, 432),
}

# Capacitor splash density folders → (width, height).
SPLASH = {
    "drawable": (2732, 2732),
    "drawable-port-mdpi": (320, 480),
    "drawable-port-hdpi": (480, 800),
    "drawable-port-xhdpi": (720, 1280),
    "drawable-port-xxhdpi": (960, 1600),
    "drawable-port-xxxhdpi": (1280, 1920),
    "drawable-land-mdpi": (480, 320),
    "drawable-land-hdpi": (800, 480),
    "drawable-land-xhdpi": (1280, 720),
    "drawable-land-xxhdpi": (1600, 960),
    "drawable-land-xxxhdpi": (1920, 1280),
}


def load_source() -> Image.Image:
    for name in ("icon.png", "logo.png"):
        path = BRANDING / name
        if path.is_file():
            img = Image.open(path).convert("RGBA")
            print(f"source={path} size={img.size} format={Image.open(path).format}")
            return img
    raise FileNotFoundError("No branding/icon.png or branding/logo.png found")


def content_bbox(img: Image.Image) -> tuple[int, int, int, int]:
    """Tight box around non-transparent, non-near-black pixels."""
    px = img.load()
    w, h = img.size
    minx, miny, maxx, maxy = w, h, -1, -1
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            if a > 12 and (r + g + b) > 40:
                minx = min(minx, x)
                miny = min(miny, y)
                maxx = max(maxx, x)
                maxy = max(maxy, y)
    if maxx < 0:
        return (0, 0, w, h)
    # Small padding so edges are not clipped after resize.
    pad = 4
    return (
        max(0, minx - pad),
        max(0, miny - pad),
        min(w, maxx + 1 + pad),
        min(h, maxy + 1 + pad),
    )


def fit_contain(
    src: Image.Image,
    canvas_size: int | tuple[int, int],
    *,
    cover_ratio: float = 0.72,
    bg: tuple[int, int, int, int] = BG,
) -> Image.Image:
    """Letterbox src onto a black canvas without distortion."""
    if isinstance(canvas_size, int):
        cw = ch = canvas_size
    else:
        cw, ch = canvas_size

    canvas = Image.new("RGBA", (cw, ch), bg)
    max_w = max(1, int(cw * cover_ratio))
    max_h = max(1, int(ch * cover_ratio))
    scale = min(max_w / src.width, max_h / src.height)
    nw = max(1, int(round(src.width * scale)))
    nh = max(1, int(round(src.height * scale)))
    resized = src.resize((nw, nh), Image.Resampling.LANCZOS)
    x = (cw - nw) // 2
    y = (ch - nh) // 2
    canvas.alpha_composite(resized, (x, y))
    return canvas


def save_png(img: Image.Image, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    # Flatten to RGB for splash/legacy when fully opaque black bg — keep RGBA for icons.
    img.save(path, format="PNG", optimize=True)
    print(f"wrote {path.relative_to(ROOT)} ({img.size[0]}x{img.size[1]})")


def main() -> None:
    raw = load_source()
    box = content_bbox(raw)
    mark = raw.crop(box)
    print(f"content_bbox={box} mark_size={mark.size}")

    # Persist a normalized true-PNG working copy for tooling (does not replace sources).
    work = BRANDING / "generated"
    work.mkdir(exist_ok=True)
    save_png(mark, work / "mark.png")
    save_png(fit_contain(mark, 1024, cover_ratio=0.82), work / "launcher-1024.png")
    save_png(fit_contain(mark, (2732, 2732), cover_ratio=0.55), work / "splash-master.png")

    for folder, (launcher, foreground) in LAUNCHER.items():
        base = RES / folder
        # Full launcher (legacy + round) — mark inside safe circle-ish margin.
        icon = fit_contain(mark, launcher, cover_ratio=0.78)
        save_png(icon, base / "ic_launcher.png")
        save_png(icon, base / "ic_launcher_round.png")
        # Adaptive foreground: more padding so OEM masks do not crop the mark.
        fg = fit_contain(mark, foreground, cover_ratio=0.56)
        save_png(fg, base / "ic_launcher_foreground.png")

    for folder, size in SPLASH.items():
        # Splash: logo prominently centered, still letterboxed (no stretch).
        splash = fit_contain(mark, size, cover_ratio=0.58)
        # SplashScreen theme expects a bitmap drawable named splash.
        out = splash.convert("RGB")  # opaque black bg
        save_png(out, RES / folder / "splash.png")

    # Adaptive icon background color → black.
    (RES / "values" / "ic_launcher_background.xml").write_text(
        '<?xml version="1.0" encoding="utf-8"?>\n'
        "<resources>\n"
        "    <color name=\"ic_launcher_background\">#000000</color>\n"
        "</resources>\n",
        encoding="utf-8",
    )
    (RES / "drawable" / "ic_launcher_background.xml").write_text(
        '<?xml version="1.0" encoding="utf-8"?>\n'
        "<vector xmlns:android=\"http://schemas.android.com/apk/res/android\"\n"
        '    android:width="108dp"\n'
        '    android:height="108dp"\n'
        '    android:viewportHeight="108"\n'
        '    android:viewportWidth="108">\n'
        '    <path android:fillColor="#000000"\n'
        '          android:pathData="M0,0h108v108h-108z"/>\n'
        "</vector>\n",
        encoding="utf-8",
    )

    # Prefer PNG foreground over default Capacitor vector.
    anydpi = RES / "mipmap-anydpi-v26"
    for name in ("ic_launcher.xml", "ic_launcher_round.xml"):
        (anydpi / name).write_text(
            '<?xml version="1.0" encoding="utf-8"?>\n'
            "<adaptive-icon xmlns:android=\"http://schemas.android.com/apk/res/android\">\n"
            '    <background android:drawable="@color/ic_launcher_background"/>\n'
            '    <foreground android:drawable="@mipmap/ic_launcher_foreground"/>\n'
            "</adaptive-icon>\n",
            encoding="utf-8",
        )

    print("Branding assets generated.")


if __name__ == "__main__":
    main()
