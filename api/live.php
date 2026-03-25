<?php
// Set timezone to match the server's expected logic
date_default_timezone_set('Asia/Dhaka');

// 1. Get the URL from the AJAX request
$url = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($url)) {
    die("Error: No ID provided");
}

// 2. Fetch the HTML from the blog post
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
]);
$html = curl_exec($ch);
curl_close($ch);

if (!$html) {
    die("Error: Failed to fetch HTML from source.");
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
$waitTime = (int)get_config_value('waitTime', $html, true);

$dataId = '';
if (preg_match('/data-id=["\']([^"\']+)["\']/', $html, $idMatches)) {
    $dataId = $idMatches[1];
}

// Validation
if (!$backend || !$pass) {
    die("Error: Could not extract stream configuration.");
}

// 4. API Fetch Function with Session Fix
function fetchApi($apiUrl, $referer) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_REFERER => $referer,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// 5. FIXED XOR Decryption Function
function decryptHexXOR($hex, $key) {
    // Clean the hex string from any non-hex characters (spaces, etc)
    $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);
    $result = "";
    $keyLen = strlen($key);
    for ($i = 0; $i < strlen($hex); $i += 2) {
        $hexByte = substr($hex, $i, 2);
        // Using @ to suppress hexdec warnings and ensure it only processes valid bytes
        $charCode = @hexdec($hexByte) ^ ord($key[($i / 2) % $keyLen]);
        $result .= chr($charCode);
    }
    return $result;
}

// --- STEP 1: Initialize Session ---
$initResponse = fetchApi($backend . "?action=init", $url);

if (!isset($initResponse['data']) || $initResponse['status'] !== 'success') {
    die("Error: Session Failed");
}
$sessionToken = $initResponse['data'];

// --- STEP 2: Anti-Bot Wait ---
// Note: On Vercel, if waitTime > 10, this script may time out.
// If it fails, move this sleep to the JavaScript side.
if ($waitTime > 0 && $waitTime < 10) {
    sleep($waitTime);
}

// --- STEP 3: Generate Auth ID ---
$currentHour = (int)date('G'); 
$secretNum = ($currentHour * $mathMul) + $mathAdd;
$finalAuthID = $prefix . $dataId . "_" . $secretNum;

// --- STEP 4: Fetch Encrypted Link ---
$fetchUrl = $backend . "?action=getLink&sessToken=" . urlencode($sessionToken) . "&id=" . urlencode($finalAuthID);
$linkResponse = fetchApi($fetchUrl, $url);

if (!isset($linkResponse['data']) || $linkResponse['status'] !== 'success') {
    die("Error: Link fetch failed.");
}

// --- STEP 5: Decrypt and Output ---
$finalM3uLink = decryptHexXOR($linkResponse['data'], $pass);

// --- STEP 6: Get Redirect ---
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $finalM3uLink,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false, 
    CURLOPT_HEADER => true,
    CURLOPT_USERAGENT => "okhttp/4.12.0",
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$response = curl_exec($ch);
$redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
curl_close($ch);

// Output strictly plain text
header("Content-Type: text/plain");
echo $redirectUrl ? trim($redirectUrl) : trim($finalM3uLink);
?>
