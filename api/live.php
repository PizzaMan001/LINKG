<?php
// Set timezone to match the server's expected logic
date_default_timezone_set('Asia/Dhaka');

// 1. Get the URL from the AJAX request
$url = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($url)) {
    header("Content-Type: text/plain");
    die("Error: No ID provided");
}

// 2. Fetch the HTML from the source
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
]);
$html = curl_exec($ch);
curl_close($ch);

if (!$html) {
    header("Content-Type: text/plain");
    die("Error: Failed to fetch source HTML.");
}

// 3. Extract Configuration Values using Regex
function get_config_value($key, $content, $is_numeric = false) {
    $pattern = $is_numeric 
        ? '/\b' . $key . '\s*:\s*(\d+)/' 
        : '/\b' . $key . '\s*:\s*["\']([^"\']*)["\']/';
    
    if (preg_match($pattern, $content, $matches)) {
        return $matches[1];
    }
    return null;
}

$backend  = get_config_value('backend', $html);
$pass      = get_config_value('pass', $html);
$prefix   = get_config_value('prefix', $html);
$mathMul  = (int)get_config_value('mathMul', $html, true);
$mathAdd  = (int)get_config_value('mathAdd', $html, true);

$dataId = '';
if (preg_match('/data-id=["\']([^"\']+)["\']/', $html, $idMatches)) {
    $dataId = $idMatches[1];
}

if (!$backend || !$pass) {
    header("Content-Type: text/plain");
    die("Error: Configuration extraction failed.");
}

// 4. API Fetch Function
function fetchApi($apiUrl, $referer) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_REFERER => $referer,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Safari/537.36'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// 5. Robust XOR Decryption (Fixed for PHP 8.x)
function decryptHexXOR($hex, $key) {
    // Scrub non-hex characters to prevent "Invalid character" warnings
    $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);
    $result = "";
    $keyLen = strlen($key);
    for ($i = 0; $i < strlen($hex); $i += 2) {
        $hexByte = substr($hex, $i, 2);
        if (strlen($hexByte) === 2) {
            // Silence conversion warnings with @
            $charCode = @hexdec($hexByte) ^ ord($key[($i / 2) % $keyLen]);
            $result .= chr($charCode);
        }
    }
    return $result;
}

// --- STEP 1: Initialize Session ---
$initResponse = fetchApi($backend . "?action=init", $url);
if (!isset($initResponse['data'])) {
    header("Content-Type: text/plain");
    die("Error: Session Init Failed");
}
$sessionToken = $initResponse['data'];

// --- STEP 2: Generate Auth ID ---
$currentHour = (int)date('G'); 
$secretNum = ($currentHour * $mathMul) + $mathAdd;
$finalAuthID = $prefix . $dataId . "_" . $secretNum;

// --- STEP 3: Fetch Encrypted Link ---
$fetchUrl = $backend . "?action=getLink&sessToken=" . urlencode($sessionToken) . "&id=" . urlencode($finalAuthID);
$linkResponse = fetchApi($fetchUrl, $url);

if (!isset($linkResponse['data'])) {
    header("Content-Type: text/plain");
    die("Error: getLink failed");
}

// --- STEP 4: Decrypt Link ---
$decryptedLink = decryptHexXOR($linkResponse['data'], $pass);

// --- STEP 5: Follow Redirect for Final Link ---
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => trim($decryptedLink),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false, // We manually catch the redirect
    CURLOPT_HEADER => true,
    CURLOPT_USERAGENT => "okhttp/4.12.0",
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 5
]);
$res = curl_exec($ch);
$finalRedirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
curl_close($ch);

// --- FINAL OUTPUT ---
header("Content-Type: text/plain");
echo $finalRedirect ? trim($finalRedirect) : trim($decryptedLink);
?>
