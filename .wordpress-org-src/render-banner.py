"""Render banner.html and icon.html to the WP.org asset sizes.

Usage: python3 render-banner.py [out-dir]
Writes banner-1544x500.png, banner-772x250.png, icon-256x256.png, icon-128x128.png.
Each size is rendered natively (the designs scale with the viewport width), not downscaled.
"""
import pathlib
import sys

from playwright.sync_api import sync_playwright

here = pathlib.Path(__file__).resolve().parent
out = pathlib.Path(sys.argv[1]) if len(sys.argv) > 1 else here.parent / '.wordpress-org'
out.mkdir(parents=True, exist_ok=True)

jobs = [('banner.html', '#banner', 1544, 500), ('banner.html', '#banner', 772, 250), ('icon.html', '#icon', 256, 256), ('icon.html', '#icon', 128, 128)]

with sync_playwright() as p:
    browser = p.chromium.launch()
    for src, sel, w, h in jobs:
        page = browser.new_page(viewport={'width': w, 'height': h})
        page.goto((here / src).as_uri(), wait_until='networkidle')
        page.evaluate('document.fonts.ready')
        page.wait_for_timeout(300)
        name = ('banner' if src == 'banner.html' else 'icon') + f'-{w}x{h}.png'
        page.locator(sel).screenshot(path=str(out / name))
        page.close()
    browser.close()
