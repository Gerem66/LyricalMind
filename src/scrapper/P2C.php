<?php

    /**
     * Get lyrics from Paroles2Chanson
     * @param string $artists
     * @param string $title
     * @return string|false
     */
    function GetLyricsFromP2C($artists, $title, &$source) {
        // Format Title
        $formatTitle = strtolower($title);
        $formatTitle = str_replace('Ç', 'c', $formatTitle);
        $formatTitle = str_replace('ç', 'c', $formatTitle);
        $formatTitle = str_replace(' & ', ' ', $formatTitle);
        $formatTitle = str_replace(',', '', $formatTitle);
        $formatTitle = str_replace('’', '-', $formatTitle);
        $formatTitle = str_replace('\'', '-', $formatTitle);
        $formatTitle = str_replace('"', '-', $formatTitle);
        $formatTitle = str_replace(':', '-', $formatTitle);
        $formatTitle = str_replace('!', '', $formatTitle);
        $formatTitle = str_replace('@', '', $formatTitle);
        $formatTitle = str_replace(' - ', '-', $formatTitle);
        $formatTitle = str_replace('é', 'e', $formatTitle);
        $formatTitle = str_replace('...', '', $formatTitle);
        $formatTitle = str_replace('.', '-', $formatTitle);
        $formatTitle = trim($formatTitle);
        $formatTitle = join('-', explode(' ', $formatTitle));
        $formatTitle = stripAccents($formatTitle);
        
        // Format Artists
        $artists = strtolower($artists);
        if (strpos($artists, ',')) // Get First
            $artists = explode(',', $artists)[0];
        $artists = str_replace('\'', '-', $artists);
        $artists = join('-', explode(' ', $artists));
        $artists = stripAccents($artists);

        // Get lyrics
        $p2c_url = "https://paroles2chansons.lemonde.fr/paroles-$artists/paroles-$formatTitle.html";
        $source = $p2c_url;
        $lyrics = RequestWithProxy($p2c_url);
        $lyrics = strip_tags($lyrics);
        $lyrics = explode('/* paroles2chansons.com - au dessus des paroles*/', $lyrics)[1];
        $lyrics = explode('/* paroles2chansons.com - Below Lyrics */', $lyrics)[0];
        $lyrics = explode("\n", $lyrics);

        $blackwords = [
            'googletag',
            '}',
            'window.',
            'adUnitName',
            'subtag',
            'cf_page_artist',
            'cf_page_song',
            'cf_page_genre',
            'cf_page_song',
            'cf_adunit_id'
        ];
        foreach ($blackwords as $word) {
            $lyrics = array_filter($lyrics, fn($l) => strpos($l, $word) === false);
        }

        // Remove empty lines
        //$lyrics = array_filter($lyrics, fn($l) => strlen($l) > 0);

        $lyrics = implode("\n", $lyrics);
        $lyrics = ClearLyrics($lyrics);
        //print_r(str_replace("\n", '<br />', $lyrics));
        $lines = explode("\n", $lyrics);
        for ($i = 0; $i < count($lines); $i++) {
            if (strpos($lines[$i], 'google_ad_client') !== false || strpos($lines[$i], 'google_ad_slot') !== false ||
                strpos($lines[$i], 'google_ad_width') !== false || strpos($lines[$i], 'google_ad_height') !== false ||
                strpos($lines[$i], '/*') !== false || strpos($lines[$i], '*/') !== false) {
                    unset($lines[$i]);
                }
        }
        $lyrics = join("\n", $lines);

        $lyricsFound = $lyrics ? true : false;
        //if (!$lyricsFound && strlen($title) > 5) {
        //    $title = substr($title, 1);
        //    $lyrics = GetLyricsFromP2C($artists, $title);
        //    return $lyrics;
        //}

        return $lyricsFound ? $lyrics : false;
    }


?>