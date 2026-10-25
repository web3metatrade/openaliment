"""Decode generated QR symbols using an independent ZXing decoder, not the encoder."""
import json
import sys
import re
import os
import xml.etree.ElementTree as ET
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
import zxingcpp
from PIL import Image, ImageDraw
cases = json.loads((ROOT / 'tests/runtime/qr-matrices.json').read_text(encoding='utf-8'))
checks = []
for case in cases:
    scale = 8
    size = (case['size'] + 8) * scale
    image = Image.new('L', (size, size), 255)
    draw = ImageDraw.Draw(image)
    for y, row in enumerate(case['modules']):
        for x, black in enumerate(row):
            if black:
                x0, y0 = (x + 4) * scale, (y + 4) * scale
                draw.rectangle((x0, y0, x0 + scale - 1, y0 + scale - 1), fill=0)
    decoded = zxingcpp.read_barcode(image)
    assert decoded and decoded.text == case['text'], case['text']
    checks.append({'url':case['text'],'passed':True,'modules':case['size'],'quiet_zone':4})
    print('PASS independent QR decode', case['text'])
svg_path = ROOT / 'tests/runtime/browser-qr.svg'
if os.environ.get('OA_TEST_BROWSER_QR') == '1':
    assert svg_path.exists() and (ROOT / 'tests/runtime/browser-qr.expected.txt').exists(), 'Download the isolated browser QR fixture first.'
    root = ET.fromstring(svg_path.read_text(encoding='utf-8'))
    size = int(root.attrib['viewBox'].split()[-1])
    image = Image.new('L', (size * 8, size * 8), 255)
    draw = ImageDraw.Draw(image)
    for element in root.iter():
        if element.tag.endswith('path'):
            for x, y in re.findall(r'M(\d+),(\d+)h1v1h-1z', element.attrib.get('d','')):
                x0, y0 = int(x) * 8, int(y) * 8
                draw.rectangle((x0, y0, x0 + 7, y0 + 7), fill=0)
    decoded = zxingcpp.read_barcode(image)
    expected = (ROOT / 'tests/runtime/browser-qr.expected.txt').read_text(encoding='utf-8').strip()
    assert decoded and decoded.text == expected
    checks.append({'name':'Actual SVG downloaded from browser UI','passed':True,'url':expected})
    print('PASS actual browser SVG independently decoded')
(ROOT / 'tests/runtime/qr-report.json').write_text(json.dumps({'suite':'Independent QR decode','decoder':'zxing-cpp 3.1.1','passed':len(checks),'failed':0,'checks':checks}, indent=2), encoding='utf-8')
