<?php
date_default_timezone_set('Asia/Dhaka');

$url = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($url)) die("Error: No ID provided");

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Safari/537.36'
]);
$html = curl_exec($ch);
curl_close($ch);

if (!$html) die("Error: Failed to fetch source.");

function get_config_value($key, $content, $is_numeric = false) {
    $pattern = $is_numeric ? '/\b' . $key . '\s*:\s*(\d+)/' : '/\b' . $key . '\s*:\s*["\']([^"\']*)["\']/';
    if (preg_match($pattern, $content, $matches)) return $matches[1];
    return null;
}

$backend = get_config_value('backend', $html);
$pass = get_config_value('pass', $html);
$prefix = get_config_value('prefix', $html);
$mathMul = (int)get_config_value('mathMul', $html, true);
$mathAdd = (int)get_config_value('mathAdd', $html, true);

$dataId = '';
if (preg_match('/data-id=["\']([^"\']+)["\']/', $html, $idMatches)) $dataId = $idMatches[1];

function fetchApi($apiUrl, $referer) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_REFERER => $referer,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Safari/537.36'
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function decryptHexXOR($hex, $key) {
    $result = "";
    $keyLen = strlen($key);
    for ($i = 0; $i < strlen($hex); $i += 2) {
        $hexByte = substr($hex, $i, 2);
        $result .= chr(hexdec($hexByte) ^ ord($key[($i / 2) % $keyLen]));
    }
    return $result;
}

$initResponse = fetchApi($backend . "?action=init", $url);
if (!isset($initResponse['data'])) die("Error: Session Failed");

$sessionToken = $initResponse['data'];
// SLEEP REMOVED - HANDLED BY FRONTEND TIMER

$currentHour = (int)date('G'); 
$secretNum = ($currentHour * $mathMul) + $mathAdd;
$finalAuthID = $prefix . $dataId . "_" . $secretNum;

$linkResponse = fetchApi($backend . "?action=getLink&sessToken=" . urlencode($sessionToken) . "&id=" . urlencode($finalAuthID), $url);
if (!isset($linkResponse['data'])) die("Error: Link fetch failed.");

$finalM3uLink = decryptHexXOR($linkResponse['data'], $pass);

// Get final redirect
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $finalM3uLink,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER => true,
    CURLOPT_USERAGENT => "okhttp/4.12.0",
    CURLOPT_SSL_VERIFYPEER => false
]);
$res = curl_exec($ch);
$redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
curl_close($ch);

header("Content-Type: text/plain");
echo $redirect ? $redirect : $finalM3uLink;
