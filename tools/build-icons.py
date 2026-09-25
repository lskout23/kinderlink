"""Generate KinderLink's original PNG icons. Optional build dependency: Pillow.

The geometry/colors mirror public/kinderlink-mark.svg. No external images are used.
Run from any directory; only the two public/icons PNG assets are written.
"""
from pathlib import Path
from PIL import Image, ImageDraw

SCALE = 16
SIZE = 128 * SCALE
CREAM = '#fff8ef'


def link(cx, angle, color, width):
    layer = Image.new('RGBA', (SIZE, SIZE))
    draw = ImageDraw.Draw(layer)
    # SVG stroke is centered on the rounded rectangle's outline.
    x, y, w, h, radius = cx - 15, 30, 30, 64, 15
    half = width / 2
    outer = [(x-half)*SCALE, (y-half)*SCALE, (x+w+half)*SCALE, (y+h+half)*SCALE]
    inner = [(x+half)*SCALE, (y+half)*SCALE, (x+w-half)*SCALE, (y+h-half)*SCALE]
    draw.rounded_rectangle(outer, radius=(radius+half)*SCALE, fill=color)
    draw.rounded_rectangle(inner, radius=(radius-half)*SCALE, fill=(0, 0, 0, 0))
    return layer.rotate(-angle, resample=Image.Resampling.BICUBIC, center=(cx*SCALE, 62*SCALE))


def main():
    # Full-bleed cream background for maskable icons; link geometry stays in safe zone.
    image = Image.new('RGBA', (SIZE, SIZE), CREAM)
    image.alpha_composite(link(54, -40, '#087f83', 10))
    image.alpha_composite(link(74, 40, CREAM, 16))
    image.alpha_composite(link(74, 40, '#e87d6c', 10))
    target = Path(__file__).resolve().parents[1] / 'public' / 'icons'
    target.mkdir(parents=True, exist_ok=True)
    for size in (192, 512):
        image.convert('RGB').resize((size, size), Image.Resampling.LANCZOS).save(target / f'icon-{size}.png')
        print(f'Generated KinderLink icon: {size}x{size}')


if __name__ == '__main__':
    main()