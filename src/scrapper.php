<?php

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/scrapper/AZ.php';
require_once __DIR__ . '/scrapper/Genius.php';
require_once __DIR__ . '/scrapper/P2C.php';

/**
 * Return lyrics of a song
 * @param string $artists
 * @param string $title
 * @param function $callback
 * @return string|false
 */
function scrapper($artists, $title, &$source, $callback = null) {
    $lyrics = false;
    $scrapFuncs = array(
        'Genius' => 'GetLyricsFromGenius',
        'AZ' => 'GetLyricsFromAZ',
        'P2C' => 'GetLyricsFromP2C'
    );

    foreach ($scrapFuncs as $scrapName => $scrapFunc) {
        if ($callback !== null) {
            $callback($scrapName);
        }
        $lyrics = $scrapFunc($artists, $title, $source);
        if ($lyrics !== false) break;
    }

    // Reset source
    if ($lyrics === false) {
        $source = null;
    }

    // Format source
    if ($source !== null) {
        $source = str_replace('https://', '', $source);
        $source = str_replace('http://', '', $source);
        if (substr($source, -1) === '/') {
            $source = substr($source, 0, -1);
        }
    }

    return $lyrics;
}

?>