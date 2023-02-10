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
        $formatTitle = str_replace(' & ', ' ', $formatTitle);
        $formatTitle = str_replace(['!', '@', '...', ',', '(', ')'], '', $formatTitle);
        $formatTitle = str_replace(['’', '\'', '"', ':', '.', ' - '], '', $formatTitle);
        $formatTitle = trim($formatTitle);
        $formatTitle = str_replace(' ', '-', $formatTitle);
        $formatTitle = stripAccents($formatTitle);
        
        // Format Artists
        $artists = strtolower($artists);
        if (strpos($artists, ',')) // Get First
            $artists = explode(',', $artists)[0];
        $artists = str_replace('\'', '-', $artists);
        $artists = str_replace(' ', '-', $artists);
        $artists = stripAccents($artists);

        // Get lyrics
        $p2c_url = "https://paroles2chansons.lemonde.fr/paroles-$artists/paroles-$formatTitle.html";
        $source = $p2c_url;

        $up_partition = '/* paroles2chansons.com - au dessus des paroles*/';
        $down_partition = '/* paroles2chansons.com - Below Lyrics */';

        $lyrics = RequestWithProxy($p2c_url, required: $up_partition);
        if ($lyrics === false) return false;

        if (strpos($lyrics, $up_partition) === false ||
            strpos($lyrics, $down_partition) === false) {
            return false;
        }

        $lyrics = strip_tags($lyrics);
        $lyrics = explode($up_partition, $lyrics)[1];
        $lyrics = explode($down_partition, $lyrics)[0];
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

        $lines = explode("\n", $lyrics);
        for ($i = 0; $i < count($lines); $i++) {
            if (strpos($lines[$i], 'google_ad_client') !== false || strpos($lines[$i], 'google_ad_slot') !== false ||
                strpos($lines[$i], 'google_ad_width') !== false || strpos($lines[$i], 'google_ad_height') !== false ||
                strpos($lines[$i], '/*') !== false || strpos($lines[$i], '*/') !== false) {
                    unset($lines[$i]);
                }
        }
        $lyrics = join("\n", $lines);
        return $lyrics ? $lyrics : false;
    }


?>