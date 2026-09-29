"""Render banner.html to the WP.org banner sizes.

Usage: python3 render-banner.py [light|dark] [out-dir]
Writes banner-1544x500.png and banner-772x250.png.
"""
import pathlib
import sys

from PIL import Image
from playwright.sync_api import sync_playwright

here = pathlib.Path(__file__).resolve().parent
variant = sys.argv[1] if len(sys.argv) > 1 else 'light'
out = pathlib.Path(sys.argv[2]) if len(sys.argv) > 2 else here.parent / '.wordpress-org'
out.mkdir(parents=True, exist_ok=True)

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_page(viewport={'width': 1544, 'height': 500}, device_scale_factor=1)
    page.goto((here / 'banner.html').as_uri(), wait_until='networkidle')
    if variant == 'dark':
        page.evaluate("document.body.classList.add('dark')")
    page.evaluate('document.fonts.ready')
    page.wait_for_timeout(300)
    big = out / 'banner-1544x500.png'
    page.locator('#banner').screenshot(path=str(big))
    browser.close()

Image.open(big).resize((772, 250), Image.LANCZOS).save(out / 'banner-772x250.png', optimize=True)
print('wrote', big, 'and banner-772x250.png')
