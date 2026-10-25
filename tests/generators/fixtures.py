"""Create synthetic OCR photos locally; no consumer or production images are used."""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[2]
runtime = ROOT / 'tests/runtime'
runtime.mkdir(parents=True, exist_ok=True)
image = Image.new('RGB', (900, 600), '#f2ead7')
draw = ImageDraw.Draw(image)
draw.rectangle((90, 90, 810, 510), fill='#cce0b8', outline='#124e42', width=8)
draw.text((160, 210), 'ALIMENT DE PROBA', fill='#124e42', font=ImageFont.load_default(size=48))
image.save(runtime / 'consumer-no-code.jpg', quality=90)
image = Image.new('RGB', (1100, 330), 'white')
draw = ImageDraw.Draw(image)
draw.text((55, 65), 'EXP 05.11.2026', fill='black', font=ImageFont.load_default(size=110))
image.save(runtime / 'consumer-date.jpg', quality=95)
print('Synthetic package and date photos generated.')
