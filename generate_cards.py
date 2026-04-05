"""
generate_cards.py — run this to rebuild cards.json from your images folder.

Folder structure:
  images/schweiz/        → Schweizer Briefmarken
  images/china/          → Chinesische Briefmarken
  images/deutschland/    → Deutsche Briefmarken

Naming format for files and collage folders:
  Name_Year_Price.jpg    e.g.  Helvetia_1900_25.jpg
  Name_Price.jpg         e.g.  Sondermarke_50.jpg       (no year)
  Name.jpg               e.g.  Sondermarke.jpg          (name only)
  Use - for spaces:      Pro-Juventute → "Pro Juventute"

Collage: create a subfolder with the same naming format.
  Flugpostmarken_1919-1947_150/
    img1.jpeg
    img2.jpeg

Run:  python generate_cards.py
"""

import os, json, re
from urllib.parse import quote

BASE  = os.path.dirname(os.path.abspath(__file__))
IMG   = os.path.join(BASE, 'images')
OUT   = os.path.join(BASE, 'cards.json')

IMAGE_EXTS = {'.jpg', '.jpeg', '.png', '.webp', '.gif'}
CATEGORIES = {
    'schweiz':      'images/schweiz',
    'china':        'images/china',
    'deutschland':  'images/deutschland',
}

def is_year_like(s):
    """True if string looks like a year or year range (e.g. 1962, 1919-1947, 1928-und-1939)"""
    return bool(re.search(r'(18|19|20)\d{2}', s))

def is_price_like(s):
    """True if string is a plain number (price in CHF)"""
    return bool(re.match(r'^\d+([.,]\d+)?$', s))

def parse_name(raw):
    """
    Parse folder/filename into (name, year, price).
    Scans from right: last plain number = price, then year-like part = year,
    everything remaining = name.
    """
    raw = os.path.splitext(raw)[0].strip()
    parts = [p.strip() for p in raw.split('_')]

    year  = ''
    price = ''

    # From the right: grab price first (plain number)
    if parts and is_price_like(parts[-1]):
        price = parts.pop()

    # Then grab year (contains 4-digit year pattern)
    if parts and is_year_like(parts[-1]):
        year = parts.pop()

    # Remaining parts = name (replace - with space, title-case)
    name = ' '.join(p.replace('-', ' ') for p in parts).strip().title()

    return name, year, price

def make_desc(year):
    return ('Jahrgang ' + year) if year else ''

def scan_category(folder_path, url_prefix):
    cards = []
    if not os.path.isdir(folder_path):
        return cards

    entries = sorted(os.listdir(folder_path))
    for entry in entries:
        full = os.path.join(folder_path, entry)
        ext  = os.path.splitext(entry)[1].lower()

        if os.path.isdir(full):
            # Collage: all images inside subfolder
            imgs = sorted([
                f for f in os.listdir(full)
                if os.path.splitext(f)[1].lower() in IMAGE_EXTS
            ])
            if not imgs:
                continue
            name, year, price = parse_name(entry)
            card = {
                'id':     re.sub(r'[^a-z0-9]', '', name.lower()) + '_' + str(len(cards)+1),
                'name':   name,
                'desc':   make_desc(year),
                'price':  price,
                'images': [url_prefix + '/' + quote(entry) + '/' + quote(img) for img in imgs]
            }
            cards.append(card)

        elif ext in IMAGE_EXTS:
            name, year, price = parse_name(entry)
            card = {
                'id':    re.sub(r'[^a-z0-9]', '', name.lower()) + '_' + str(len(cards)+1),
                'name':  name,
                'desc':  make_desc(year),
                'price': price,
                'image': url_prefix + '/' + quote(entry)
            }
            cards.append(card)

    return cards

def main():
    result = {}
    for key, rel_path in CATEGORIES.items():
        folder = os.path.join(BASE, rel_path.replace('/', os.sep))
        cards  = scan_category(folder, rel_path)
        result[key] = cards
        print(f'  {key}: {len(cards)} cards')
        for c in cards:
            imgs = c.get('images', [c.get('image','')])
            print(f'    [{len(imgs)} img] {c["name"]} | Jahr: {c["desc"] or "-"} | CHF {c["price"] or "-"}')

    with open(OUT, 'w', encoding='utf-8') as f:
        json.dump(result, f, ensure_ascii=False, indent=2)

    print(f'\nDone! cards.json updated ({sum(len(v) for v in result.values())} cards total).')

if __name__ == '__main__':
    main()
