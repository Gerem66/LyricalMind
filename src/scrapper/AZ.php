<?php

/**
 * Get lyrics from AZ
 * @param string $artists
 * @param string $title
 * @return string|false
 */
function GetLyricsFromAZ($artists, $title, &$source) {
    // Artists rename - artiste1artiste2artiste3
    $artists = strtolower($artists);
    $charstoremove = [ ' et ', ' & ', '.', 'è', '-', ' ', '$' ];
    foreach ($charstoremove as $c)
        $artists = str_replace($c, '', $artists);
    if (strpos($artists, ',')) $artists = explode(',', $artists)[0];
    if (substr($artists, 0, 4) == 'the ') $artists = substr($artists, 4);

    // Title rename - word1word2word3
    $title = str_replace(' ', '', $title);
    $title = strtolower($title);
    $title = CleanText($title);
    if (strpos($title, '(feat')) $title = explode('(feat', $title)[0];
    if (strpos($title, 'ft.')) $title = explode('ft.', $title)[0];
    $charstoremove = [ ' et ', ' & ', '-', '\'', '.', ',', 'ô', 'ê', '(', ')', ' ' ];
    foreach ($charstoremove as $c)
        $title = str_replace($c, '', $title);

    // Get Lyrics from AZLyrics
    $url = "http://azlyrics.com/lyrics/$artists/$title.html";
    $source = $url;
    //$lyrics = file_get_contents($url);

    $up_partition = '<!-- Usage of azlyrics.com content by any third-party lyrics provider is prohibited by our licensing agreement. Sorry about that. -->';
    $down_partition = '<!-- MxM banner -->';

    $lyrics = RequestWithProxy($url, required: $up_partition);
    if ($lyrics === false) return false;

    if (strpos($lyrics, $up_partition) === false ||
        strpos($lyrics, $down_partition) === false)
    {
        return false;
    }

    // Find and format lyrics
    $lyrics = explode($up_partition, $lyrics)[1];
    $lyrics = explode($down_partition, $lyrics)[0];
    $lyrics = CleanLyrics($lyrics);

    return !!$lyrics ? $lyrics : false;
}

?>
