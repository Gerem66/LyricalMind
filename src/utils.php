<?php

function stripAccents($str) {
    $from = 'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ';
    $to = 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY';
    return strtolower(strtr($str, $from, $to));
}

function CleanText($text) {
    $text = preg_replace('/[[:punct:]]/', '', $text);
    return strtolower($text);
}

/**
 * Clear lyrics
 * @param string $lyrics
 * @return string
 */
function CleanLyrics($lyrics) {
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

    // For each verse, remove the first line if starts and ends with brackets
    $verses = explode("\n\n", $lyrics);
    foreach ($verses as $key => $verse) {
        $lines = explode("\n", $verse);
        if (count($lines) > 1) {
            $firstLine = $lines[0];
            $lastLine = $lines[count($lines) - 1];
            if (substr($firstLine, 0, 1) === '[' &&
                substr($firstLine, -1) === ']')
            {
                array_shift($lines);
                $verses[$key] = implode("\n", $lines);
            }
            if (substr($lastLine, 0, 1) === '[' &&
                substr($lastLine, -1) === ']')
            {
                array_pop($lines);
                $verses[$key] = implode("\n", $lines);
            }
        }
    }
    $lyrics = implode("\n\n", $verses);

    return $lyrics;
}

/**
 * @param AssemblyAIWord[] $referenceWords
 * @param int $index
 * @param int $offset
 * @return AssemblyAIWord[]|false
 */
function GetRefWordsFromIndex($referenceWords, $index, $offset) {
    $index = max(0, $index - $offset);
    $length = min(2 * $offset + 1, count($referenceWords) - $index);
    return array_slice($referenceWords, $index, $length);
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

?>