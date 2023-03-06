<?php

$error_codes = [
    'ERR_ACCESS_DENIED', 
    'ERR_CONNECT_FAIL', 
    'ERR_CONNECTION_CLOSED', 
    'ERR_CONNECTION_RESET', 
    'ERR_CONNECTION_REFUSED', 
    'ERR_CONNECTION_ABORTED', 
    'ERR_CONNECTION_TIMED_OUT', 
    'ERR_EMPTY_RESPONSE', 
    'ERR_NAME_NOT_RESOLVED', 
    '503 Service Unavailable', 
    '502 Bad Gateway', 
    '504 Gateway Time-out', 
    '500 Internal Server Error', 
    '403 Forbidden', 
    '404 Not Found', 
    '400 Bad Request', 
    '408 Request Timeout', 
    '429 Too Many Requests'
];


/**
 * Use proxies.json to get a random proxy from http://free-proxy.cz/en/proxylist/country/all/http/ping/level1
 * @param string $url URL to request
 * @param int $timeout Timeout in seconds
 * @param int $retry Number of retry if failed, with a new random proxy
 * @param string $required Required string in the content
 * @return string|false Content of the URL, or false if failed or no proxy available
 * @throws Exception If proxies file not found
 */
function RequestWithProxy($url, $timeout = 5, $retry = 10, $required = '') {
    global $error_codes;

    // Check if proxies file exists
    $proxiesFile = __DIR__ . '/proxies.txt';
    if (!file_exists($proxiesFile)) {
        throw new Exception("Proxies file not found: {$proxiesFile}");
    }

    // List of proxy
    $proxiesRaw = file_get_contents($proxiesFile);
    $proxies = explode("\n", $proxiesRaw);

    // Random proxy
    $proxy = $proxies[array_rand($proxies)];

    // Init curl and request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36');

    // Get content
    $content = curl_exec($ch);
    $cherrno = curl_errno($ch);
    curl_close($ch);

    // Check if content is valid
    $status = true;
    foreach ($error_codes as $error_code) {
        if (strpos($content, $error_code) !== false) {
            $status = false;
            break;
        }
    }

    // Check if content contains required string
    if ($required && strpos($content, $required) === false) {
        $status = false;
    }

    // Retry if failed
    if ($cherrno || !$content || !$status) {
        if ($retry > 0) {
            return RequestWithProxy($url, $timeout, $retry - 1, $required);
        }
        return false;
    }

    return $content;
}

?>