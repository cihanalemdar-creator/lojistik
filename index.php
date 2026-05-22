<?php
session_start();

$site = [
    'name' => 'İstanbul İhracat Merkezi',
    'short' => 'İİM',
    'tool' => 'Nakliye Çözümleri',
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

$services = [
    ['Komple Tır Taşımacılığı', 'Şehirlerarası tek seferlik veya düzenli yükler için komple araç planlaması yapılır.'],
    ['Parsiyel Yük Taşıma', 'Tam araç gerektirmeyen yükler için uygun taşıma modeli ve rota alternatifleri değerlendirilir.'],
    ['Frigorifik ve Hassas Yükler', 'Sıcaklık kontrollü veya özel dikkat gerektiren yükler için araç tipi ve operasyon şartları netleştirilir.'],
    ['İhracat ve İthalat Yükleri', 'Dış ticaret yüklerinde evrak, teslim şekli ve sevk planı dikkate alınarak taşıma süreci organize edilir.'],
];

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function money(float $value): string { return number_format(round($value / 500) * 500, 0, ',', '.') . ' TL'; }
function clean_text(string $value, int $max = 160): string { return mb_substr(trim(preg_replace('/\s+/', ' ', $value)), 0, $max); }
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
    return ['km' => $km, 'coefficient' => $k, 'low' => $low, 'high' => $high];
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$result = null;
$calcError = '';
$quoteError = '';
$quoteMailto = '';
$quoteSummary = [];
$route = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    $intent = (string)($_POST['intent'] ?? '');

    if (!hash_equals($_SESSION['csrf'], $token)) {
        if ($intent === 'quote') $quoteError = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyin.';
        else $calcError = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyin.';
    } elseif ($intent === 'quote') {
        $quoteSummary = [
            'Ad / Firma' => clean_text((string)($_POST['name'] ?? ''), 80),
            'Telefon' => clean_text((string)($_POST['phone'] ?? ''), 40),
            'Çıkış' => clean_text((string)($_POST['origin'] ?? ''), 80),
            'Varış' => clean_text((string)($_POST['destination'] ?? ''), 80),
            'Yük Bilgisi' => clean_text((string)($_POST['cargo'] ?? ''), 220),
        ];
        if ($quoteSummary['Telefon'] === '' || $quoteSummary['Çıkış'] === '' || $quoteSummary['Varış'] === '') {
            $quoteError = 'Telefon, çıkış ve varış bilgisi zorunludur.';
        } else {
            $body = "Nakliye teklif talebi\n\n";
            foreach ($quoteSummary as $label => $value) { $body .= $label . ': ' . $value . "\n"; }
            $quoteMailto = 'mailto:' . $site['email'] . '?subject=' . rawurlencode('Nakliye Teklif Talebi') . '&body=' . rawurlencode($body);
        }
    } else {
        $route = trim((string)($_POST['route'] ?? ''));
        $manual = filter_input(INPUT_POST, 'distance', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 1, 'max_range' => 2500]]);
        $km = $manual ?: ($routes[$route] ?? 0);
        if ($km > 0 && $km <= 2500) $result = calculate((float)$km, $pricing);
        else $calcError = 'Lütfen geçerli bir rota seçin veya 1-2500 km arasında mesafe girin.';
    }
}

$faq = [
    ['Şehirlerarası nakliye teklifi almak için hangi bilgiler gerekir?', 'Çıkış ve varış ili, yük tipi, yaklaşık tonaj, araç tipi, yükleme tarihi ve iletişim bilgisi yeterlidir. Detay netleştikçe teklif kesinleşir.'],
    ['Komple tır nakliye fiyatı neden değişir?', 'Mesafe, yakıt maliyeti, araç tipi, yükleme tarihi, dönüş yükü ihtimali, otoyol giderleri ve araç bulunabilirliği fiyatı etkiler.'],
    ['Hesaplama aracı kesin fiyat verir mi?', 'Hayır. Hesaplama aracı tahmini KDV hariç fiyat aralığı üretir. Kesin teklif için yük ve operasyon bilgileri ayrıca değerlendirilir.'],
];

$schema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        ['@type' => 'Organization', 'name' => $site['name'], 'url' => $site['url'], 'email' => $site['email'], 'telephone' => $site['phone'], 'address' => $site['address']],
        ['@type' => 'Service', 'name' => 'Şehirlerarası Yük Taşıma ve Nakliye Teklifi', 'provider' => ['@type' => 'Organization', 'name' => $site['name']], 'areaServed' => 'Türkiye', 'serviceType' => ['Komple tır taşımacılığı', 'Parsiyel yük taşıma', 'Şehirlerarası nakliye']],
        ['@type' => 'FAQPage', 'mainEntity' => array_map(fn($item) => ['@type' => 'Question', 'name' => $item[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item[1]]], $faq)]
    ]
];
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Şehirlerarası Yük Taşıma ve Nakliye Teklifi | İİM Nakliye Çözümleri</title>
    <meta name="description" content="Şehirlerarası yük taşıma, komple tır nakliye, parsiyel yük taşıma ve hızlı nakliye teklifi için İİM Nakliye Çözümleri. Yük bilgilerinizi paylaşın, uygun araç ve taşıma modeli için dönüş alın.">
    <meta name="keywords" content="şehirlerarası nakliye, yük taşıma, komple tır nakliye, parsiyel nakliye, nakliye teklifi, tır nakliye fiyatları, şehirlerarası yük taşıma">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="https://iim.com.tr/nakliye-hesapla/">
    <meta property="og:title" content="Şehirlerarası Yük Taşıma ve Nakliye Teklifi">
    <meta property="og:description" content="Komple tır, parsiyel yük ve şehirlerarası nakliye ihtiyaçlarınız için hızlı teklif ve operasyon takibi.">
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
        <nav class="menu" aria-label="Ana menü"><a href="#hizmetler">Hizmetler</a><a href="#nasil-calisir">Nasıl Çalışır?</a><a href="#hesapla">Ücret Hesapla</a><a href="#iletisim">İletişim</a></nav>
        <a class="cta small" href="#teklif">Teklif Al</a>
    </div>
</header>

<main id="top">
<section class="hero">
    <div class="wrap hero-grid">
        <div class="hero-copy">
            <p class="eyebrow">Şehirlerarası nakliye ve yük taşıma</p>
            <h1>Şehirlerarası yüklerinizi güvenle taşıyoruz.</h1>
            <p class="lead">Komple tır, parsiyel ve özel yük taşımalarında doğru araç planlaması, hızlı nakliye teklifi ve teslimata kadar operasyon takibi sunuyoruz.</p>
            <div class="actions"><a class="cta" href="#teklif">Yüküm İçin Teklif Al</a><a class="ghost" href="#hesapla">Tahmini Ücreti Gör</a></div>
            <div class="trust"><span>Komple tır</span><span>Parsiyel yük</span><span>Şehirlerarası taşıma</span><span>Tek muhatapla takip</span></div>
        </div>
        <section class="calc" id="teklif" aria-labelledby="quote-title">
            <h2 id="quote-title">Hızlı teklif talebi</h2>
            <p>Yük bilgilerinizi bırakın; rota, araç tipi ve taşıma modeli için ön değerlendirme yapalım.</p>
            <?php if ($quoteError): ?><div class="alert error"><?= h($quoteError) ?></div><?php endif; ?>
            <?php if ($quoteMailto): ?>
                <div class="alert success">Talep özeti hazırlandı. Bilgileri e-posta ile göndermek için aşağıdaki butonu kullanın.</div>
                <a class="cta full" href="<?= h($quoteMailto) ?>">Talebi E-posta ile Gönder</a>
            <?php endif; ?>
            <form method="post" action="#teklif">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                <input type="hidden" name="intent" value="quote">
                <label>Ad / Firma <input name="name" maxlength="80" placeholder="Firma veya ad soyad" value="<?= h((string)($_POST['name'] ?? '')) ?>"></label>
                <label>Telefon <input name="phone" maxlength="40" placeholder="Telefon numaranız" value="<?= h((string)($_POST['phone'] ?? '')) ?>" required></label>
                <label>Çıkış ili / bölgesi <input name="origin" maxlength="80" placeholder="Örn: İstanbul Hadımköy" value="<?= h((string)($_POST['origin'] ?? '')) ?>" required></label>
                <label>Varış ili / bölgesi <input name="destination" maxlength="80" placeholder="Örn: Ankara OSB" value="<?= h((string)($_POST['destination'] ?? '')) ?>" required></label>
                <label>Yük bilgisi <input name="cargo" maxlength="220" placeholder="Örn: 12 palet, yaklaşık 8 ton, tenteli araç" value="<?= h((string)($_POST['cargo'] ?? '')) ?>"></label>
                <button class="cta full" type="submit">Teklif Talebi Oluştur</button>
            </form>
        </section>
    </div>
</section>

<section class="section" id="hizmetler">
    <div class="wrap center"><p class="eyebrow blue">Nakliye hizmetleri</p><h2>Yükünüz için uygun taşıma çözümünü planlıyoruz.</h2><p class="sub">Tek seferlik yükler, düzenli sevkiyatlar, ihracat yükleri ve şehirlerarası araç ihtiyaçları için net, takip edilebilir ve ticari olarak uygulanabilir çözümler.</p></div>
    <div class="wrap cards">
        <?php foreach ($services as $index => $service): ?><article><b><?= h((string)($index + 1)) ?></b><h3><?= h($service[0]) ?></h3><p><?= h($service[1]) ?></p></article><?php endforeach; ?>
    </div>
</section>

<section class="section soft" id="nasil-calisir">
    <div class="wrap split">
        <div><p class="eyebrow blue">Nasıl çalışır?</p><h2>Yük bilgisi alınır, uygun araç ve rota planlanır.</h2><p class="sub">Amaç yalnızca fiyat vermek değil; yükleme, sevk ve teslimat sürecinin tek muhatapla takip edilebilir hale gelmesidir.</p></div>
        <div class="routes"><span>1. Yük ve rota bilgisi alınır <b>Ön kontrol</b></span><span>2. Araç tipi ve taşıma modeli belirlenir <b>Planlama</b></span><span>3. Fiyat ve termin bilgisi paylaşılır <b>Teklif</b></span><span>4. Yükleme ve teslimat takip edilir <b>Operasyon</b></span></div>
    </div>
</section>

<section class="section" id="hesapla">
    <div class="wrap split">
        <div>
            <p class="eyebrow blue">Yardımcı fiyat aracı</p>
            <h2>Tahmini nakliye ücretinizi görün.</h2>
            <p class="sub">Bu araç, rota mesafesi ve güncel yakıt maliyetlerine göre KDV hariç tahmini fiyat aralığı üretir. Kesin teklif; yük tipi, araç uygunluğu, yükleme tarihi ve operasyon koşullarına göre netleşir.</p>
        </div>
        <section class="calc" aria-labelledby="calc-title">
            <h2 id="calc-title">Tahmini ücret hesapla</h2>
            <?php if ($calcError): ?><div class="alert error"><?= h($calcError) ?></div><?php endif; ?>
            <form method="post" action="#hesapla">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                <input type="hidden" name="intent" value="calculate">
                <label>Popüler rota
                    <select name="route">
                        <option value="">Rota seçin</option>
                        <?php foreach ($routes as $name => $km): ?>
                            <option value="<?= h($name) ?>" <?= $route === $name ? 'selected' : '' ?>><?= h($name) ?> - <?= h((string)$km) ?> km</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Mesafe (km) <input name="distance" inputmode="decimal" type="number" min="1" max="2500" step="0.1" placeholder="Örn: 1080" value="<?= h((string)($_POST['distance'] ?? '')) ?>"></label>
                <button class="cta full" type="submit">Tahmini Ücreti Göster</button>
            </form>
            <?php if ($result): ?>
                <div class="result" role="status"><span>Tahmini taşıma fiyat aralığı</span><strong><?= h(money($result['low'])) ?> - <?= h(money($result['high'])) ?></strong><em>KDV hariç</em><dl><div><dt>Mesafe</dt><dd><?= h((string)$result['km']) ?> km</dd></div><div><dt>Katsayı</dt><dd><?= h((string)$result['coefficient']) ?></dd></div><div><dt>Motorin</dt><dd><?= h((string)$pricing['diesel_price']) ?> TL/L</dd></div></dl></div>
            <?php endif; ?>
        </section>
    </div>
</section>

<section class="section soft" id="rotalar">
    <div class="wrap split">
        <div><p class="eyebrow blue">Sık çalışılan rotalar</p><h2>Türkiye genelinde şehirlerarası yük taşıma rotaları.</h2><p class="sub">İstanbul, Ankara, İzmir, Bursa, Mersin, Gaziantep, Kocaeli ve İskenderun çıkışlı komple tır ve parsiyel yük talepleri için hızlı ön değerlendirme yapılabilir.</p></div>
        <div class="routes"><?php foreach ($routes as $name => $km): ?><span><?= h($name) ?><b><?= h((string)$km) ?> km</b></span><?php endforeach; ?></div>
    </div>
</section>

<section class="section">
    <div class="wrap split">
        <div><p class="eyebrow blue">Güven veren yaklaşım</p><h2>Nakliye fiyatı kadar operasyon takibi de önemlidir.</h2></div>
        <div class="text"><p>Şehirlerarası yük taşıma sürecinde doğru aracı bulmak kadar, yükleme saatinin, iletişimin, teslimat planının ve evrak akışının takip edilmesi de kritiktir.</p><p>İİM Nakliye Çözümleri; yük bilgisine göre taşıma modelini değerlendirir, fiyat ve termin bilgisini netleştirir, sevkiyat sürecinde tek muhatapla ilerlemenizi sağlar.</p></div>
    </div>
</section>

<section class="section" id="sss">
    <div class="wrap center"><p class="eyebrow blue">Sık sorulan sorular</p><h2>Şehirlerarası nakliye teklifi hakkında</h2></div>
    <div class="wrap cards">
        <?php foreach ($faq as $item): ?><article><h3><?= h($item[0]) ?></h3><p><?= h($item[1]) ?></p></article><?php endforeach; ?>
    </div>
</section>

<section class="contact" id="iletisim">
    <div class="wrap contact-box">
        <div><p class="eyebrow">İletişim</p><h2>Yükünüz hazırsa, taşıma planını birlikte netleştirelim.</h2><p>Çıkış ve varış noktası, yük tipi, tonaj ve araç ihtiyacını paylaşın; size uygun şehirlerarası nakliye çözümü için dönüş yapalım.</p></div>
        <address><strong><?= h($site['name']) ?></strong><br><?= h($site['address']) ?><br><a href="tel:+902129098705"><?= h($site['phone']) ?></a><br><a href="tel:+908505223403"><?= h($site['phone_alt']) ?></a><br><a href="mailto:<?= h($site['email']) ?>"><?= h($site['email']) ?></a></address>
    </div>
</section>
</main>

<footer class="footer"><div class="wrap foot"><span>© <?= date('Y') ?> <?= h($site['name']) ?></span><span>Şehirlerarası yük taşıma, komple tır ve parsiyel nakliye çözümleri.</span></div></footer>
<script src="assets/app.js" defer></script>
</body>
</html>
