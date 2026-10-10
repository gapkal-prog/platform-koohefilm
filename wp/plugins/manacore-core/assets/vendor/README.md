# کتابخانه‌های شخص ثالث (Vendored)

فایل‌های این پوشه از بیرون پروژه می‌آیند و **دست‌نخورده** نگه داشته می‌شوند.
قرار دادنشان در مخزن (به‌جای فراخوانی از CDN) عمدی است: سایت‌های فارسی
مخاطب ما اغلب به CDN دسترسی پایدار ندارند و افزونه‌ی فروشی نباید به سرویس
بیرونی وابسته باشد.

| فایل | بسته | نسخه | مجوز | منبع |
|---|---|---|---|---|
| `hls/hls.min.js` | `hls.js` | 1.7.3 | Apache-2.0 | https://github.com/video-dev/hls.js |

## به‌روزرسانی

```bash
npm pack hls.js@<version>
tar xzf hls.js-<version>.tgz
cp package/dist/hls.min.js wp/plugins/manacore-core/assets/vendor/hls/hls.min.js
cp package/LICENSE        wp/plugins/manacore-core/assets/vendor/hls/LICENSE
```

پس از هر به‌روزرسانی، `wp/tests/test-player-pro.cjs` را اجرا کنید؛ نسخه و
مجوز را می‌سنجد و مطمئن می‌شود فایل سالم و کامل است.

## کاربرد

`hls.min.js` تنها زمانی بارگذاری می‌شود که برگه‌ی پخش منبع `.m3u8` داشته
باشد (`data-player-media="hls"` روی عنصر `<video>`). مرورگرهایی که HLS را
بومی پخش می‌کنند (Safari/iOS) به این فایل نیازی ندارند و بارگذاری نمی‌کنند.
