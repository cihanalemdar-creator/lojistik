# İİM Nakliye Hesapla

Tek sayfalı, PHP tabanlı ve cPanel uyumlu şehirlerarası komple tır nakliye ücreti hesaplama sayfası.

## Özellikler

- Tamamen Türkçe tek sayfalı landing page
- Yakıt bazlı komple tır fiyat hesaplama
- KDV hariç tahmini fiyat aralığı
- İİM kurumsal mavi renk yaklaşımı
- Harici font/kütüphane kullanmaz
- SEO meta, Open Graph ve JSON-LD içerir
- cPanel / XAMPP uyumlu

## Hesaplama Modeli

```text
Yakıt Maliyeti = Mesafe x Yakıt Tüketimi x Motorin Fiyatı
Tahmini Ücret = Yakıt Maliyeti x Mesafe Katsayısı + Ek Masraf + Servis Payı
```

Varsayılanlar:

- Motorin: 68,79 TL/L
- Tüketim: 35-40 L / 100 km
- Katsayı: 2,50'den 2,10'a kademeli düşer
- Ek masraf: 6.000 TL
- Servis payı: 1.000 TL
- Sonuç: KDV hariç

## Lokal Kullanım

```bash
php -S localhost:8000
```

veya XAMPP içinde:

```text
C:\xampp\htdocs\lojistik
```

Tarayıcı:

```text
http://localhost/lojistik
```
