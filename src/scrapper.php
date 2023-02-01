<?php

    require_once __DIR__ . '/utils.php';
    require_once __DIR__ . '/scrapper/AZ.php';
    require_once __DIR__ . '/scrapper/P2C.php';

    /**
     * Return lyrics of a song
     * @param string $artists
     * @param string $title
     * @return string|false
     */
    function scrapper($artists, $title) {
        $lyrics = false;
        $scrapFuncs = array(
            'GetLyricsFromAZ',
            'GetLyricsFromP2C'
        );

        foreach ($scrapFuncs as $source) {
            $lyrics = $source($artists, $title);
            if ($lyrics !== false) break;
        }

        return $lyrics;
    }

?>