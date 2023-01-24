<?php

    function GetLyrics($artists, $title) {
        $lyrics = GetLyricsFromAZ($artists, $title);
        if ($lyrics === false) $lyrics = GetLyricsFromP2C($artists, $title);
        return $lyrics;
    }

    /**
     * Get lyrics from AZ
     * @param string $artists
     * @param string $title
     * @return string|false
     */
    function GetLyricsFromAZ($artists, $title) {
        // Artists rename - artiste1artiste2artiste3
        $artists = strtolower($artists);
        $charstoremove = [ ' et ', ' & ', '.', 'è', '-', ' ' ];
        foreach ($charstoremove as $c)
            $artists = str_replace($c, '', $artists);
        if (strpos($artists, ',')) $artists = explode(',', $artists)[0];
        if (substr($artists, 0, 4) == 'the ') $artists = substr($artists, 4);

        // Title rename - word1word2word3
        $title = str_replace(' ', '', $title);
        $title = strtolower($title);
        $title = stripAccents($title);
        if (strpos($title, '(feat')) $title = explode('(feat', $title)[0];
        if (strpos($title, 'ft.')) $title = explode('ft.', $title)[0];
        $charstoremove = [ ' et ', ' & ', '-', '\'', '.', ',', 'ô', 'ê', '(', ')', ' ' ];
        foreach ($charstoremove as $c)
            $title = str_replace($c, '', $title);
        
        // Get Lyrics from AZLyrics
        $url = "http://azlyrics.com/lyrics/$artists/$title.html";
        $lyrics = file_get_contents($url);

        // Lyrics Filter
        $up_partition = '<!-- Usage of azlyrics.com content by any third-party lyrics provider is prohibited by our licensing agreement. Sorry about that. -->';
        $down_partition = '<!-- MxM banner -->';
        $lyrics = explode($up_partition, $lyrics)[1];
        $lyrics = explode($down_partition, $lyrics)[0];
        $lyrics = ClearLyrics($lyrics);
        
        return $lyrics ? $lyrics : false;
    }

    /**
     * Get lyrics from Paroles2Chanson
     * @param string $artists
     * @param string $title
     * @return string|false
     */
    function GetLyricsFromP2C($artists, $title) {
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
        //print_r(file_get_contents($p2c_url));
        $lyrics = strip_tags(file_get_contents($p2c_url));
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
        if (!$lyricsFound && strlen($title) > 5) {
            $title = substr($title, 1);
            $lyrics = GetLyricsFromP2C($artists, $title);
            return $lyrics;
        }

        return $lyricsFound ? $lyrics : false;
    }

    //$lyrics = GetLyricsFromP2C("josman", "j'allume");
    //$lyrics = GetLyricsFromP2C("Hayce Lemsi", "Electron libre");
    //$lyrics = GetLyricsFromAZ("Hayce Lemsi", "Electron libre");
    //print_r($lyrics);

?>