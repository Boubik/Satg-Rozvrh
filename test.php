<?php
// Testovací skript pro získání rozvrhu učitele přes API

// Definujte základní proměnné
$username = 'loffel';  // Zadejte své API uživatelské jméno
$password = 'demo';  // Zadejte své API heslo
$stagLogin  = 'baroch';             // Zadejte stagLogin (uživatelské jméno ve stagu)
$semestr = '%';                  // Můžete specifikovat konkrétní semestr, nebo ponechat "%" pro vše

// ------------------------------
// 1. Získání teacher id na základě stagLogin
// ------------------------------

// Sestavení URL pro API volání učitelského ID
$teacherIdApiUrl = sprintf(
    'https://stag-demo.zcu.cz/ws/services/rest2/ucitel/getUcitIdnoByStagLogin?stagLogin=%s',
    urlencode($stagLogin)
);

// Vytvoření Basic Authentication hlavičky
$auth = base64_encode($username . ':' . $password);

// Vytvoření stream contextu s potřebnou hlavičkou
$opts = [
    "http" => [
        "method"  => "GET",
        "header"  => "Authorization: Basic " . $auth . "\r\n",
        "timeout" => 15
    ]
];
$context = stream_context_create($opts);

// Získání teacher id z API
$response = file_get_contents($teacherIdApiUrl, false, $context);
if ($response === false) {
    die("Chyba při získávání teacher id z API.\n");
}

// ✅ API vrací přímo číslo → použijeme `trim()` k odstranění bílých znaků
$teacherId = trim($response);

if (!is_numeric($teacherId) || empty($teacherId)) {
    die("Nebyl nalezen teacher id pro stagLogin: $stagLogin.\n");
}

echo "Získané teacher ID: $teacherId\n";

// ------------------------------
// 2. Získání rozvrhu učitele pomocí získaného teacher id
// ------------------------------

// Sestavení URL pro API volání rozvrhu učitele
$rozvrhApiUrl = sprintf(
    'https://stag-demo.zcu.cz/ws/services/rest2/rozvrhy/getRozvrhByUcitel?ucitIdno=%s&semestr=%s&outputFormat=XML',
    urlencode($teacherId),
    urlencode($semestr)
);

// Získání rozvrhu učitele z API
$responseRozvrh = file_get_contents($rozvrhApiUrl, false, $context);
if ($responseRozvrh === false) {
    die("Chyba při získávání dat o rozvrhu učitele.\n");
}

// Načtení XML dat z rozvrhu
$xmlRozvrh = simplexml_load_string($responseRozvrh);
if (!$xmlRozvrh) {
    die("Chyba při zpracování XML dat rozvrhu učitele.\n");
}

// Protože root element je namespaced (<ns2:rozvrh>), získáme jeho děti
$rozvrh = $xmlRozvrh->children();
if (!$rozvrh) {
    die("Nebyla nalezena žádná data o rozvrhu.\n");
}

// ------------------------------
// 3. Výpis rozvrhu učitele
// ------------------------------
foreach ($rozvrh->rozvrhovaAkce as $akce) {
    $nazev      = isset($akce->nazev) ? (string)$akce->nazev : 'Nedefinováno';
    $predmet    = isset($akce->predmet) ? (string)$akce->predmet : 'Nedefinováno';
    $typAkce    = isset($akce->typAkce) ? (string)$akce->typAkce : '';

    // Některé akce mohou mít definovaný den a časy
    $den         = isset($akce->den) ? (string)$akce->den : 'Den neuveden';
    $hodinaOd    = isset($akce->hodinaOd) ? (string)$akce->hodinaOd : '';
    $hodinaDo    = isset($akce->hodinaDo) ? (string)$akce->hodinaDo : '';
    $hodinaSkutOd = isset($akce->hodinaSkutOd) ? (string)$akce->hodinaSkutOd : '';
    $hodinaSkutDo = isset($akce->hodinaSkutDo) ? (string)$akce->hodinaSkutDo : '';

    echo "<p>";
    echo "Akce: $nazev, Předmět: $predmet, Typ: $typAkce. ";
    if ($den !== 'Den neuveden') {
        echo "Den: $den. ";
    }
    if ($hodinaOd && $hodinaDo) {
        echo "Plánovaně: od $hodinaOd do $hodinaDo. ";
    }
    if ($hodinaSkutOd && $hodinaSkutDo) {
        echo "Skutečně: od $hodinaSkutOd do $hodinaSkutDo.";
    }
    echo "</p>";
}
