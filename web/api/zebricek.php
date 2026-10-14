<?php
/*
 * Žebříček pro hru Víš proč? (avisproc.cz)
 *
 * Ukládá jen přezdívku, body, počet trefených hádanek a čas uložení.
 * Žádné IP adresy, e-maily ani příjmení. Každé zařízení se může zapsat jednou (první dohraná hra).
 * Data leží v souboru zebricek-data.php vedle tohoto skriptu; první řádek souboru brání jeho zobrazení v prohlížeči.
 */
ini_set('display_errors', '0');
error_reporting(0);

// Heslo pro mazání záznamů na stránce https://avisproc.cz/?spravce
// Heslo není v Gitu: leží jen na hostingu v souboru api/spravce-heslo.php s jediným řádkem
//   <?php return 'tvoje-heslo-aspon-10-znaku';
// Nahrání z GitHubu ten soubor nikdy nepřepíše. Když soubor chybí, mazání je vypnuté.
// Soubor se jen čte jako text (nespouští se), takže překlep v něm nic neprozradí ani nerozbije.
function spravce_heslo()
{
    $raw = @file_get_contents(__DIR__ . '/spravce-heslo.php');
    return ($raw !== false && preg_match('/return\s*[\'"]([^\'"]{10,})[\'"]\s*;/', $raw, $m)) ? $m[1] : '';
}
define('SPRAVCE_HESLO', spravce_heslo());

const DATA = __DIR__ . '/zebricek-data.php';
const LIMITY = __DIR__ . '/zebricek-limity.php';
const ZAMEK = "<?php http_response_code(404); exit; ?>\n";
const HADANEK = 10;   // hádanek v jedné hře (sada karet)
const ZA_TREFU = 10;
const BONUS = 5;
const TOP = 50;
const MAX_ZAZNAMU = 3000;
const LIMIT_POCET = 60;   // max. zápisů z jedné adresy…
const LIMIT_OKNO = 600;   // …za 10 minut (celá třída může chodit přes jednu školní IP)

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function konec($kod, $data)
{
    http_response_code($kod);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function otevri($cesta)
{
    $fh = @fopen($cesta, 'c+');
    if (!$fh || !flock($fh, LOCK_EX)) {
        konec(500, ['ok' => false, 'chyba' => 'uloziste']);
    }
    return $fh;
}

function nacti($fh)
{
    rewind($fh);
    $raw = stream_get_contents($fh);
    $nl = ($raw === false) ? false : strpos($raw, "\n");
    if ($nl === false) {
        return [];
    }
    $data = json_decode(substr($raw, $nl + 1), true);
    return is_array($data) ? $data : [];
}

function uloz($fh, $data)
{
    rewind($fh);
    ftruncate($fh, 0);
    fwrite($fh, ZAMEK . json_encode($data, JSON_UNESCAPED_UNICODE));
    fflush($fh);
}

function serad($zaznamy)
{
    usort($zaznamy, function ($a, $b) {
        if ($a['b'] !== $b['b']) return $b['b'] - $a['b'];
        if ($a['s'] !== $b['s']) return $b['s'] - $a['s'];
        return $a['t'] - $b['t'];   // při shodě vede ten, kdo se zapsal dřív
    });
    return $zaznamy;
}

function verejny($z, $i)
{
    return ['id' => $z['id'], 'misto' => $i + 1, 'jmeno' => $z['j'], 'body' => $z['b'], 'spravne' => $z['s'], 'kdy' => $z['t']];
}

function prehled($zaznamy, $otisk)
{
    $zaznamy = serad($zaznamy);
    $top = [];
    $ja = null;
    foreach ($zaznamy as $i => $z) {
        if ($i < TOP) $top[] = verejny($z, $i);
        if ($otisk !== '' && hash_equals($z['d'], $otisk)) $ja = verejny($z, $i);
    }
    return ['ok' => true, 'celkem' => count($zaznamy), 'top' => $top, 'ja' => $ja];
}

// Body musí jít v této hře opravdu získat: 10 za trefu, +5 za každou další trefu v řadě.
function mozne_body($spravne, $body)
{
    if ($spravne === 0) return $body === 0;
    $max_serii = min($spravne, HADANEK - $spravne + 1);
    for ($serie = 1; $serie <= $max_serii; $serie++) {
        if ($body === ZA_TREFU * $spravne + BONUS * ($spravne - $serie)) return true;
    }
    return false;
}

function bez_diakritiky($s)
{
    $mapa = [
        'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's',
        'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z', 'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ľ' => 'l', 'ĺ' => 'l', 'ŕ' => 'r', 'ô' => 'o',
        'Á' => 'a', 'Č' => 'c', 'Ď' => 'd', 'É' => 'e', 'Ě' => 'e', 'Í' => 'i', 'Ň' => 'n', 'Ó' => 'o', 'Ř' => 'r', 'Š' => 's',
        'Ť' => 't', 'Ú' => 'u', 'Ů' => 'u', 'Ý' => 'y', 'Ž' => 'z', 'Ä' => 'a', 'Ö' => 'o', 'Ü' => 'u', 'Ľ' => 'l', 'Ĺ' => 'l', 'Ŕ' => 'r', 'Ô' => 'o',
    ];
    return strtolower(strtr($s, $mapa));
}

function prezdivka($jmeno)
{
    $jmeno = trim(preg_replace('/\s+/u', ' ', (string) $jmeno));
    if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._-]{1,15}$/u', $jmeno)) return null;
    $hladke = preg_replace('/[^a-z0-9]/', '', bez_diakritiky($jmeno));
    $zakazane = ['kurv', 'pica', 'picu', 'pici', 'pico', 'picus', 'kokot', 'zmrd', 'mrdk', 'mrdat', 'debil', 'curak', 'hovn',
        'sracka', 'prdel', 'nacist', 'hitler', 'penis', 'vagin', 'sex', 'fuck', 'shit', 'bitch', 'http', 'www'];
    foreach ($zakazane as $slovo) {
        if (strpos($hladke, $slovo) !== false) return null;
    }
    return $jmeno;
}

function hlidat_limit()
{
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    } else {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    $klic = substr(hash('sha256', $ip . __FILE__), 0, 16);   // jen otisk adresy, po 10 minutách se zahodí
    $ted = time();
    $fh = otevri(LIMITY);
    $limity = nacti($fh);
    foreach ($limity as $k => $casy) {
        $limity[$k] = array_values(array_filter($casy, function ($t) use ($ted) {
            return $t > $ted - LIMIT_OKNO;
        }));
        if (!$limity[$k]) unset($limity[$k]);
    }
    $moje = isset($limity[$klic]) ? $limity[$klic] : [];
    if (count($moje) >= LIMIT_POCET) {
        uloz($fh, $limity);
        fclose($fh);
        konec(429, ['ok' => false, 'chyba' => 'moc-pokusu']);
    }
    $moje[] = $ted;
    $limity[$klic] = $moje;
    uloz($fh, $limity);
    fclose($fh);
}

$metoda = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

if ($metoda === 'GET') {
    $dev = isset($_GET['dev']) ? (string) $_GET['dev'] : '';
    $otisk = preg_match('/^[a-f0-9]{32}$/', $dev) ? hash('sha256', $dev) : '';
    $fh = otevri(DATA);
    $zaznamy = nacti($fh);
    $info = fstat($fh);
    if ($info['size'] === 0) uloz($fh, []);   // první spuštění: založí prázdný žebříček
    fclose($fh);
    konec(200, prehled($zaznamy, $otisk));
}

if ($metoda !== 'POST') {
    konec(405, ['ok' => false, 'chyba' => 'metoda']);
}

$vstup = json_decode((string) file_get_contents('php://input', false, null, 0, 4096), true);
if (!is_array($vstup)) {
    konec(400, ['ok' => false, 'chyba' => 'vstup']);
}
hlidat_limit();

$akce = isset($vstup['akce']) ? $vstup['akce'] : 'ulozit';

if ($akce === 'smazat') {
    $heslo = isset($vstup['heslo']) ? (string) $vstup['heslo'] : '';
    if (SPRAVCE_HESLO === '' || !hash_equals(SPRAVCE_HESLO, $heslo)) {
        konec(403, ['ok' => false, 'chyba' => 'heslo']);
    }
    $id = isset($vstup['id']) ? (string) $vstup['id'] : '';
    $fh = otevri(DATA);
    $zaznamy = array_values(array_filter(nacti($fh), function ($z) use ($id) {
        return $z['id'] !== $id;
    }));
    uloz($fh, $zaznamy);
    fclose($fh);
    konec(200, prehled($zaznamy, ''));
}

$dev = isset($vstup['dev']) ? (string) $vstup['dev'] : '';
if (!preg_match('/^[a-f0-9]{32}$/', $dev)) {
    konec(400, ['ok' => false, 'chyba' => 'zarizeni']);
}
$jmeno = prezdivka(isset($vstup['jmeno']) ? $vstup['jmeno'] : '');
if ($jmeno === null) {
    konec(422, ['ok' => false, 'chyba' => 'jmeno']);
}
$body = isset($vstup['body']) ? $vstup['body'] : null;
$spravne = isset($vstup['spravne']) ? $vstup['spravne'] : null;
if (!is_int($body) || !is_int($spravne) || !mozne_body($spravne, $body)) {
    konec(422, ['ok' => false, 'chyba' => 'body']);
}

$otisk = hash('sha256', $dev);
$fh = otevri(DATA);
$zaznamy = nacti($fh);
foreach ($zaznamy as $z) {
    if (hash_equals($z['d'], $otisk)) {
        fclose($fh);
        $p = prehled($zaznamy, $otisk);
        $p['ok'] = false;
        $p['chyba'] = 'uz-ulozeno';
        konec(409, $p);
    }
}
if (count($zaznamy) >= MAX_ZAZNAMU) {
    fclose($fh);
    konec(507, ['ok' => false, 'chyba' => 'plno']);
}
$zaznamy[] = ['id' => bin2hex(random_bytes(6)), 'd' => $otisk, 'j' => $jmeno, 'b' => $body, 's' => $spravne, 't' => time()];
uloz($fh, $zaznamy);
fclose($fh);
konec(200, prehled($zaznamy, $otisk));
