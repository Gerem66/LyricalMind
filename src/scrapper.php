<?php

    require_once __DIR__ . '/utils.php';
    require_once __DIR__ . '/scrapper/AZ.php';
    require_once __DIR__ . '/scrapper/Genius.php';
    require_once __DIR__ . '/scrapper/P2C.php';

    /**
     * Return lyrics of a song
     * @param string $artists
     * @param string $title
     * @return string|false
     */
    function scrapper($artists, $title, &$source) {
        $lyrics = false;
        $scrapFuncs = array(
            'GetLyricsFromAZ',
            'GetLyricsFromGenius',
            'GetLyricsFromP2C'
        );

        foreach ($scrapFuncs as $scrapFunc) {
            $lyrics = $scrapFunc($artists, $title, $source);
            if ($lyrics !== false) break;
        }

        // Reset source
        if ($lyrics === false) {
            $source = null;
        }

        return $lyrics;
    }

?>