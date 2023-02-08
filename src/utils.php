<?php

function stripAccents($str) {
    $from = 'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ';
    $to = 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY';
    return strtolower(strtr($str, $from, $to));
}

function ClearLyrics($lyrics) {
    // Remove '&quot;' and '&amp;'
    $lyrics = str_replace('&quot;', '"', $lyrics);
    $lyrics = str_replace('&amp;', '&', $lyrics);

    // Remove balises
    $balises = [ '<br>', '</br>', '<div>', '</div>', '<i>', '</i>' ];
    foreach ($balises as $balise)
        $lyrics = str_replace($balise, '', $lyrics);

    // Remove white spaces at start and at end
    $lyrics = trim($lyrics, " \t\n\r\0\x0B");

    // Remove multiple \n & Remove blank lines
    $lyrics = str_replace("\r", "", $lyrics);
    for ($i = 20; $i > 2; $i--) {
        $lyrics = str_replace(str_repeat("\n", $i), "\n", $lyrics);
    }

    return $lyrics;
}

/**
 * @param int $length Length of the random string
 * @return string Random string
 */
function RandomString($length = 32) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+-*/[]{}_#!;:?%()|';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $rnd = rand(0, $charactersLength - 1);
        $randomString .= $characters[$rnd];
    }
    return $randomString;
}

/**
 * Return IP address of the client or "UNKNOWN" if failed
 * @link https://stackoverflow.com/questions/3003145/how-to-get-the-client-ip-address-in-php
 * @return string
 */
function GetClientIP() {
    $keys = array(
        'REMOTE_ADDR',
        'HTTP_FORWARDED',
        'HTTP_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED'
    );
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            return $_SERVER[$k];
        }
    }
    return 'UNKNOWN';
}

/**
 * Use proxies.json to get a random proxy from http://free-proxy.cz/en/proxylist/country/all/http/ping/level1
 * @param string $url URL to request
 * @param int $timeout Timeout in seconds
 * @param int $retry Number of retry if failed
 * @param string $required Required string in the content
 * @return string|false Content of the URL, or false if failed or no proxy available
 */
function RequestWithProxy($url, $timeout = 2, $retry = 10, $required = '') {
    $proxiesFile = __DIR__ . '/proxies.txt';
    if (!file_exists($proxiesFile)) {
        return false;
    }

    // List of proxy
    $proxiesRaw = file_get_contents($proxiesFile);
    $proxies = explode("\n", $proxiesRaw);

    // Random proxy
    $proxy = $proxies[array_rand($proxies)];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36');
    $content = curl_exec($ch);

    if (curl_errno($ch) || !$content || ($required && strpos($content, $required) === false)
        || strpos($content, 'ERR_ACCESS_DENIED') !== false
        || strpos($content, 'ERR_CONNECT_FAIL') !== false
        || strpos($content, 'ERR_CONNECTION_CLOSED') !== false
        || strpos($content, 'ERR_CONNECTION_RESET') !== false
        || strpos($content, 'ERR_CONNECTION_REFUSED') !== false
        || strpos($content, 'ERR_CONNECTION_ABORTED') !== false
        || strpos($content, 'ERR_CONNECTION_TIMED_OUT') !== false
        || strpos($content, 'ERR_EMPTY_RESPONSE') !== false
        || strpos($content, 'ERR_NAME_NOT_RESOLVED') !== false
        || strpos($content, '503 Service Unavailable') !== false
        || strpos($content, '502 Bad Gateway') !== false
        || strpos($content, '504 Gateway Time-out') !== false
        || strpos($content, '500 Internal Server Error') !== false
        || strpos($content, '403 Forbidden') !== false
        || strpos($content, '404 Not Found') !== false
        || strpos($content, '400 Bad Request') !== false
        || strpos($content, '408 Request Timeout') !== false
        || strpos($content, '429 Too Many Requests') !== false) {
        //if (curl_errno($ch))    echo('cURL error: ' . curl_error($ch));
        if ($retry > 0)         return RequestWithProxy($url, $retry - 1);
        return false;
    }

    curl_close($ch);
    return $content;
}

?>