#!/usr/bin/env python3
"""
تولید screenshot.png قالب Koohe Film (۱۲۰۰×۹۰۰).

پالت رنگ کاملاً هم‌راستا با styles/dark.json و theme.json است:
  surface   #14161a   surface-2 #1c1f26   surface-3 #252932
  foreground #eef0f4  muted     #9aa1ad   accent    #f59e0b
  border    #2c313b

اجرا:  python3 .build/screenshot.py
خروجی: screenshot.png در ریشه‌ی قالب.
"""

import os
from PIL import Image, ImageDraw, ImageFont

W, H = 1200, 900

SURFACE = (0x14, 0x16, 0x1A)
SURFACE_2 = (0x1C, 0x1F, 0x26)
SURFACE_3 = (0x25, 0x29, 0x32)
FOREGROUND = (0xEE, 0xF0, 0xF4)
MUTED = (0x9A, 0xA1, 0xAD)
ACCENT = (0xF5, 0x9E, 0x0B)
BORDER = (0x2C, 0x31, 0x3B)
ACCENT_CONTRAST = (0x11, 0x13, 0x18)

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def find_font(bold=False):
    """یافتن یک فونت TrueType موجود در سیستم."""
    candidates = [
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf" if bold
        else "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf" if bold
        else "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
    ]
    for path in candidates:
        if os.path.exists(path):
            return path
    return None


def font(size, bold=False):
    """بارگذاری فونت با اندازه‌ی دلخواه."""
    path = find_font(bold)
    if path:
        return ImageFont.truetype(path, size)
    return ImageFont.load_default()


F_H1 = font(46, True)
F_H2 = font(22, True)
F_BODY = font(15)
F_SMALL = font(13)
F_TINY = font(11)
F_LOGO = font(24, True)


def rr(draw, box, radius, fill=None, outline=None, width=1):
    """مستطیل گوشه‌گرد."""
    draw.rounded_rectangle(box, radius=radius, fill=fill, outline=outline, width=width)


def text_w(draw, s, f):
    """عرض متن."""
    return draw.textbbox((0, 0), s, font=f)[2]


img = Image.new("RGB", (W, H), SURFACE)
d = ImageDraw.Draw(img)

# ---------------------------------------------------------------- سربرگ
HEADER_H = 68
rr(d, (0, 0, W, HEADER_H), 0, fill=SURFACE_2)
d.line((0, HEADER_H, W, HEADER_H), fill=BORDER, width=1)

# نشان (کوه + ماه) — هم‌راستا با poster-placeholder.svg
d.polygon([(38, 46), (56, 22), (66, 36), (74, 26), (94, 46)], fill=ACCENT)
d.ellipse((80, 16, 94, 30), fill=ACCENT)
d.text((106, 20), "KOOHE FILM", font=F_LOGO, fill=FOREGROUND)

# ناوبری
nav = ["Home", "Movies", "Series", "Anime", "Collections"]
x = 330
for i, item in enumerate(nav):
    color = FOREGROUND if i == 0 else MUTED
    d.text((x, 26), item, font=F_BODY, fill=color)
    w = text_w(d, item, F_BODY)
    if i == 0:
        d.rounded_rectangle((x, 48, x + w, 50), radius=1, fill=ACCENT)
    x += w + 30

# جستجو + کلید حالت + حساب
rr(d, (W - 372, 18, W - 176, 50), 16, fill=SURFACE_3, outline=BORDER)
d.ellipse((W - 360, 28, W - 350, 38), outline=MUTED, width=2)
d.line((W - 352, 36, W - 347, 41), fill=MUTED, width=2)
d.text((W - 338, 26), "Search titles...", font=F_SMALL, fill=MUTED)

rr(d, (W - 162, 20, W - 116, 48), 14, fill=ACCENT)
d.ellipse((W - 144, 26, W - 122, 44), fill=SURFACE)
d.ellipse((W - 140, 28, W - 128, 40), fill=ACCENT)

rr(d, (W - 102, 18, W - 30, 50), 16, fill=SURFACE_3, outline=BORDER)
d.ellipse((W - 96, 24, W - 74, 46), fill=ACCENT)
d.text((W - 66, 27), "Pro", font=F_SMALL, fill=FOREGROUND)

# ---------------------------------------------------------------- قهرمان (Hero)
hero_top = HEADER_H + 1
hero_bot = hero_top + 330
for y in range(hero_top, hero_bot):
    t = (y - hero_top) / (hero_bot - hero_top)
    r = int(0x25 + (0x14 - 0x25) * t)
    g = int(0x29 + (0x16 - 0x29) * t)
    b = int(0x38 + (0x1A - 0x38) * t)
    d.line((0, y, W, y), fill=(r, g, b))

# بافت شبکه‌ای ملایم
for gx in range(0, W, 60):
    d.line((gx, hero_top, gx, hero_bot), fill=(0x22, 0x26, 0x2E))
for gy in range(hero_top, hero_bot, 60):
    d.line((0, gy, W, gy), fill=(0x22, 0x26, 0x2E))

# پوستر بزرگ سمت راست قهرمان
rr(d, (860, hero_top + 34, 1080, hero_top + 264), 14, fill=SURFACE_3, outline=BORDER)
d.polygon([(900, hero_top + 210), (955, hero_top + 130), (985, hero_top + 172),
           (1010, hero_top + 142), (1050, hero_top + 210)], fill=(0x3A, 0x2E, 0x1C))
d.ellipse((1020, hero_top + 66, 1050, hero_top + 96), fill=(0x4A, 0x3A, 0x1E))

# متن قهرمان
d.text((70, hero_top + 52), "FEATURED", font=F_TINY, fill=ACCENT)
d.text((70, hero_top + 76), "Cinematic Nights", font=F_H1, fill=FOREGROUND)

meta_items = ["2025", "•", "142 min", "•", "Drama", "•", "IMDb 8.7"]
mx = 70
for item in meta_items:
    col = ACCENT if "IMDb" in item else MUTED
    d.text((mx, hero_top + 142), item, font=F_SMALL, fill=col)
    mx += text_w(d, item, F_SMALL) + 10

lines = [
    "A minimal, fully block-editable WordPress theme for movie,",
    "series and anime libraries — with dark & light modes built in.",
]
for i, line in enumerate(lines):
    d.text((70, hero_top + 176 + i * 24), line, font=F_BODY, fill=MUTED)

# دکمه‌ها
rr(d, (70, hero_top + 244, 214, hero_top + 288), 22, fill=ACCENT)
d.polygon([(96, hero_top + 256), (96, hero_top + 276), (114, hero_top + 266)],
          fill=ACCENT_CONTRAST)
d.text((124, hero_top + 258), "Watch", font=F_H2, fill=ACCENT_CONTRAST)

rr(d, (228, hero_top + 244, 356, hero_top + 288), 22, outline=BORDER, width=1)
d.text((256, hero_top + 258), "+ List", font=F_H2, fill=FOREGROUND)

# ---------------------------------------------------------------- نوار فیلتر
fy = hero_bot + 26
chips = ["All", "Genre", "Year", "Country", "Quality", "Language"]
cx = 70
for i, chip in enumerate(chips):
    w = text_w(d, chip, F_SMALL) + 34
    if i == 0:
        rr(d, (cx, fy, cx + w, fy + 34), 17, fill=ACCENT)
        d.text((cx + 17, fy + 9), chip, font=F_SMALL, fill=ACCENT_CONTRAST)
    else:
        rr(d, (cx, fy, cx + w, fy + 34), 17, fill=SURFACE_2, outline=BORDER)
        d.text((cx + 17, fy + 9), chip, font=F_SMALL, fill=MUTED)
    cx += w + 10

# ---------------------------------------------------------------- شبکه‌ی پوسترها
sy = fy + 62
d.text((70, sy), "Latest Movies", font=F_H2, fill=FOREGROUND)
d.rounded_rectangle((70, sy + 32, 118, sy + 35), radius=2, fill=ACCENT)
d.text((W - 148, sy + 6), "View all →", font=F_SMALL, fill=ACCENT)

gy0 = sy + 52
COLS, GAP, MARGIN = 6, 18, 70
CARD_W = (W - MARGIN * 2 - GAP * (COLS - 1)) // COLS
CARD_H = int(CARD_W * 1.42)

tones = [
    (0x2A, 0x2E, 0x38), (0x30, 0x28, 0x22), (0x24, 0x2C, 0x34),
    (0x32, 0x2A, 0x26), (0x26, 0x2A, 0x36), (0x2E, 0x2C, 0x24),
]
titles = ["Silent Peak", "Night Route", "Blue Hour",
          "Ember", "Northwind", "Afterglow"]
ratings = ["8.9", "7.6", "8.2", "7.9", "8.5", "7.4"]

for i in range(COLS):
    cx = MARGIN + i * (CARD_W + GAP)
    rr(d, (cx, gy0, cx + CARD_W, gy0 + CARD_H), 12, fill=tones[i])

    # نقش‌مایه‌ی کوه در هر پوستر
    base = gy0 + CARD_H - 26
    d.polygon([(cx + 16, base), (cx + CARD_W // 2 - 8, base - 62),
               (cx + CARD_W // 2 + 6, base - 34),
               (cx + CARD_W // 2 + 20, base - 52),
               (cx + CARD_W - 16, base)],
              fill=(min(tones[i][0] + 22, 255), min(tones[i][1] + 20, 255),
                    min(tones[i][2] + 16, 255)))

    # نشان امتیاز
    rr(d, (cx + CARD_W - 52, gy0 + 10, cx + CARD_W - 10, gy0 + 33), 11,
       fill=(0x11, 0x13, 0x18))
    d.text((cx + CARD_W - 45, gy0 + 15), ratings[i], font=F_TINY, fill=ACCENT)

    # عنوان و سال
    d.text((cx, gy0 + CARD_H + 11), titles[i], font=F_SMALL, fill=FOREGROUND)
    d.text((cx, gy0 + CARD_H + 30), "2025", font=F_TINY, fill=MUTED)

# ---------------------------------------------------------------- پابرگ
foot_top = H - 76
d.line((0, foot_top, W, foot_top), fill=BORDER, width=1)
rr(d, (0, foot_top + 1, W, H), 0, fill=SURFACE_2)
d.text((70, foot_top + 30), "Koohe Film — Full Site Editing block theme",
       font=F_SMALL, fill=MUTED)
credit = "Designed by ManaCore"
d.text((W - 70 - text_w(d, credit, F_SMALL), foot_top + 30), credit,
       font=F_SMALL, fill=ACCENT)

out = os.path.join(ROOT, "screenshot.png")
img.save(out, "PNG", optimize=True)
print("wrote", out, img.size)
