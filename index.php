<?php
session_start();

$site = [
    'name' => 'İstanbul İhracat Merkezi',
    'short' => 'İİM',
    'tool' => 'Nakliye Hesapla',
    'phone' => '+90 (212) 909 87 05',
    'phone_alt' => '+90 (850) 522 34 03',
    'email' => 'info@iim.com.tr',
    'address' => 'EGS Business Park - Yeşilköy B-1 Blok No: 57 PK 34149 İstanbul - Türkiye',
    'url' => 'https://iim.com.tr',
];

$pricing = [
    'diesel_price' => 68.79,
    'diesel_updated_at' => '22.05.2026',
    'fuel_low' => 35,
    'fuel_high' => 40,
    'coefficient_short' => 2.50,
    'coefficient_long' => 2.10,
    'coefficient_start_km' => 100,
    'coefficient_end_km' => 1000,
    'extra_cost' => 6000,
    'service_fee' => 1000,
    'minimum_price' => 10000,
];

$routes = [
    'İskenderun - İstanbul Hadımköy' => 1080,
    'İstanbul - Ankara' => 450,
    'İstanbul - İzmir' => 480,
    'İstanbul - Bursa' => 155,
    'İstanbul - Antalya' => 700,
    'İstanbul - Mersin' => 950,
    'İstanbul - Gaziantep' => 1150,
    'Kocaeli - Ankara' => 360,
    'Bursa - İzmir' => 330,
    'Ankara - Mersin' => 500,
    'İzmir - Gaziantep' => 1110,
    'Mersin - İstanbul' => 950,
];

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function money(float $value): string { return number_format(round($value / 500) * 500, 0, ',', '.') . ' TL'; }
function coefficient(float $km, array $p): float {
    if ($km <= $p['coefficient_start_km']) return $p['coefficient_short'];
    if ($km >= $p['coefficient_end_km']) return $p['coefficient_long'];
    $range = $p['coefficient_end_km'] - $p['coefficient_start_km'];
    $drop = $p['coefficient_short'] - $p['coefficient_long'];
    return round($p['coefficient_short'] - (($km - $p['coefficient_start_km']) / $range) * $drop, 2);
}
function calculate(float $km, array $p): array {
    $k = coefficient($km, $p);
    $fuelLow = $km * ($p['fuel_low'] / 100) * $p['diesel_price'];
    $fuelHigh = $km * ($p['fuel_high'] / 100) * $p['diesel_price'];
    $low = max(($fuelLow * $k) + $p['extra_cost'] + $p['service_fee'], $p['minimum_price']);
    $high = max(($fuelHigh * $k) + $p['extra_cost'] + $p['service_fee'], $p['minimum_price']);
    return ['km' => $km, 'coefficient' => $k, 'fuel_low_cost' => $fuelLow, 'fuel_high_cost' => $fuelHigh, 'low' => $low, 'high' => $high];
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$result = null;
$error = '';
$route = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $token)) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyin.';
    } else {
        $route = trim((string)($_POST['route'] ?? ''));
        $manual = filter_input(INPUT_POST, 'distance', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 1, 'max_range' => 2500]]);
        $km = $manual ?: ($routes[$route] ?? 0);
        if ($km > 0 && $km <= 2500) $result = calculate((float)$km, $pricing);
        else $error = 'Lütfen geçerli bir rota seçin veya 1-2500 km arasında mesafe girin.';
    }
}

$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => 'İİM Nakliye Hesapla',
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Web',
    'description' => 'Şehirlerarası komple tır nakliye ücreti için yakıt bazlı tahmini fiyat hesaplama aracı.',
    'provider' => ['@type' => 'Organization', 'name' => $site['name'], 'email' => $site['email'], 'telephone' => $site['phone']]
];
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Şehirlerarası Komple Tır Nakliye Ücreti Hesaplama | İİM</title>
    <meta name="description" content="Çıkış ve varış rotasını seçin, güncel motorin fiyatı ve ortalama tır yakıt tüketimine göre KDV hariç tahmini komple tır nakliye ücretini hesaplayın.">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="https://iim.com.tr/nakliye-hesapla/">
    <meta property="og:title" content="Şehirlerarası Komple Tır Nakliye Ücreti Hesaplama">
    <meta property="og:description" content="Yakıt bazlı, hızlı ve KDV hariç komple tır nakliye fiyat aralığı hesaplama aracı.">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="tr_TR">
    <meta name="theme-color" content="#0099d8">
    <link rel="stylesheet" href="assets/app.css">
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body>
<header class="header">
    <div class="top"><div class="wrap top-in"><span><?= h($site['phone']) ?></span><span><?= h($site['email']) ?></span></div></div>
    <div class="wrap nav">
        <a class="brand" href="#top" aria-label="Anasayfa"><span class="logo">İİM</span><span><b><?= h($site['tool']) ?></b><small><?= h($site['name']) ?></small></span></a>
        <nav class="menu" aria-label="Ana menü"><a href="#hesapla">Hesapla</a><a href="#model">Model</a><a href="#rotalar">Rotalar</a><a href="#iletisim">İletişim</a></nav>
        <a class="cta small" href="#hesapla">Ücret Hesapla</a>
    </div>
</header>

<main id="top">
<section class="hero">
    <div class="wrap hero-grid">
        <div class="hero-copy">
            <p class="eyebrow">Yakıt bazlı fiyat aralığı</p>
            <h1>Şehirlerarası komple tır nakliye ücreti hesapla.</h1>
            <p class="lead">Rota veya kilometre girin; ortalama tır yakıt tüketimi, motorin fiyatı ve mesafeye göre değişen katsayı ile KDV hariç tahmini fiyat aralığını görün.</p>
            <div class="actions"><a class="cta" href="#hesapla">Hemen Hesapla</a><a class="ghost" href="#model">Hesaplama Modeli</a></div>
            <div class="trust"><span>35-40 L / 100 km</span><span>KDV hariç</span><span>Tek sayfa, hızlı PHP</span></div>
        </div>
        <section class="calc" id="hesapla" aria-labelledby="calc-title">
            <h2 id="calc-title">Nakliye ücreti hesaplama</h2>
            <p>Popüler rota seçin veya kilometreyi manuel girin.</p>
            <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
            <form method="post" action="#hesapla">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                <label>Popüler rota
                    <select name="route">
                        <option value="">Rota seçin</option>
                        <?php foreach ($routes as $name => $km): ?>
                            <option value="<?= h($name) ?>" <?= $route === $name ? 'selected' : '' ?>><?= h($name) ?> - <?= h((string)$km) ?> km</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Mesafe (km)
                    <input name="distance" inputmode="decimal" type="number" min="1" max="2500" step="0.1" placeholder="Örn: 1080" value="<?= h((string)($_POST['distance'] ?? '')) ?>">
                </label>
                <button class="cta full" type="submit">Tahmini Ücreti Hesapla</button>
            </form>
            <?php if ($result): ?>
                <div class="result" role="status">
                    <span>Tahmini komple tır ücreti</span>
                    <strong><?= h(money($result['low'])) ?> - <?= h(money($result['high'])) ?></strong>
                    <em>KDV hariç</em>
                    <dl><div><dt>Mesafe</dt><dd><?= h((string)$result['km']) ?> km</dd></div><div><dt>Katsayı</dt><dd><?= h((string)$result['coefficient']) ?></dd></div><div><dt>Motorin</dt><dd><?= h((string)$pricing['diesel_price']) ?> TL/L</dd></div></dl>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>

<section class="section" id="model">
    <div class="wrap center"><p class="eyebrow blue">Net ve anlaşılır</p><h2>Hesaplama modeli</h2><p class="sub">Bu araç kesin teklif vermez; piyasa koşullarına göre hızlı ve tutarlı bir fiyat aralığı üretir.</p></div>
    <div class="wrap cards">
        <article><b>1</b><h3>Yakıt maliyeti</h3><p>Mesafe, 35-40 L / 100 km tüketim aralığı ve motorin fiyatı ile çarpılır.</p></article>
        <article><b>2</b><h3>Mesafe katsayısı</h3><p>Kısa mesafede 2,50; uzun mesafede 2,10 katsayı kullanılır. Aradaki mesafelerde kademeli düşer.</p></article>
        <article><b>3</b><h3>Ek masraf + servis payı</h3><p>6.000 TL operasyon gideri ve 1.000 TL servis payı eklenir. Sonuç KDV hariç gösterilir.</p></article>
    </div>
</section>

<section class="section soft" id="rotalar">
    <div class="wrap split">
        <div><p class="eyebrow blue">SEO rotaları</p><h2>Popüler şehirlerarası komple tır rotaları</h2><p class="sub">İlk aşamada güçlü rotalarla başlanır; sonra Search Console verisine göre şehir bazlı sayfalar genişletilir.</p></div>
        <div class="routes"><?php foreach ($routes as $name => $km): ?><span><?= h($name) ?><b><?= h((string)$km) ?> km</b></span><?php endforeach; ?></div>
    </div>
</section>

<section class="section">
    <div class="wrap split">
        <div><p class="eyebrow blue">Güven veren yaklaşım</p><h2>Neden tek fiyat değil fiyat aralığı?</h2></div>
        <div class="text"><p>Tır fiyatı sadece kilometre değildir. Araç tipi, yükün ağırlığı, bekleme süresi, otoyol/köprü giderleri, dönüş yükü ihtimali ve araç bulunabilirliği fiyatı değiştirir. Bu nedenle hesaplayıcı kesin fiyat yerine ticari olarak daha doğru olan tahmini aralığı gösterir.</p><p>Kesin teklif için yük bilgisi, tarih, adres ve araç tipi netleşmelidir.</p></div>
    </div>
</section>

<section class="contact" id="iletisim">
    <div class="wrap contact-box">
        <div><p class="eyebrow">İletişim</p><h2>Kesin teklif için yük bilgilerinizi paylaşın.</h2><p>Rota, tonaj, yük tipi, yükleme tarihi ve araç tercihi ile daha net fiyat çalışması yapılabilir.</p></div>
        <address><strong><?= h($site['name']) ?></strong><br><?= h($site['address']) ?><br><a href="tel:+902129098705"><?= h($site['phone']) ?></a><br><a href="tel:+908505223403"><?= h($site['phone_alt']) ?></a><br><a href="mailto:<?= h($site['email']) ?>"><?= h($site['email']) ?></a></address>
    </div>
</section>
</main>

<footer class="footer"><div class="wrap foot"><span>© <?= date('Y') ?> <?= h($site['name']) ?></span><span>Şehirlerarası komple tır nakliye hesaplama aracı.</span></div></footer>
<script src="assets/app.js" defer></script>
</body>
</html>
