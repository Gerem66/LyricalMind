<?php

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
        print_r("AZ url: $url\n");
        $lyrics = file_get_contents($url);

        // Lyrics Filter
        $up_partition = '<!-- Usage of azlyrics.com content by any third-party lyrics provider is prohibited by our licensing agreement. Sorry about that. -->';
        $down_partition = '<!-- MxM banner -->';
        //print_r($lyrics);
        //print_r("\n\n\n");

        // TODO: Skip that
        $errorText = 'Our systems have detected unusual activity from your IP address (computer network)';
        if (strpos($lyrics, $errorText) !== false) {
            print_r("AZLyrics blocked us, retrying in 5 seconds...\n");
            sleep(5);
            return GetLyricsFromAZ($artists, $title);
        }

        if (strpos($lyrics, $up_partition) !== false) {
            $lyrics = explode($up_partition, $lyrics)[1];
        }
        if (strpos($lyrics, $down_partition) !== false) {
            $lyrics = explode($down_partition, $lyrics)[0];
        }
        $lyrics = ClearLyrics($lyrics);

        return $lyrics ? $lyrics : false;
    }

?>
