<?php

require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../proxy.php';

/**
 * Get lyrics from Genius
 * @param string $artists
 * @param string $title
 * @return string|false
 */
function GetLyricsFromGenius($artists, $title, &$source = null) {
    // Artists rename - main-artist
    $artists = CleanText($artists);
    $artists = str_replace(' ', '-', $artists);

    // Title rename - word1-word2-word3
    $title = CleanText($title);
    $title = str_replace(' ', '-', $title);

    // Get Lyrics from Genius
    $url = "https://genius.com/$artists-$title-lyrics";
    $source = $url;

    $lyrics = RequestWithProxy($url, retry: 20, required: 'data-lyrics-container');
    if ($lyrics === false) return false;

    // Get lyrics from html
    $lyrics = explode('<br/>', $lyrics);
    $firstExploded = explode('>', $lyrics[0]);
    $lyrics[0] = end($firstExploded);
    $lyrics[count($lyrics) - 1] = explode('<', $lyrics[count($lyrics) - 1])[0];

    $lyrics = array_map('strip_tags', $lyrics);
    $lyrics = array_map('trim', $lyrics);
    $lyrics = array_map('html_entity_decode', $lyrics);
    $lyrics = array_map(fn($l) => (!str_starts_with($l, '[') && !str_ends_with($l, ']')) ? $l : '', $lyrics);
    $lyrics = implode("\n", $lyrics);
    $lyrics = trim($lyrics);
    while (str_contains($lyrics, "\n\n\n"))
        $lyrics = str_replace("\n\n\n", "\n\n", $lyrics);

    return !!$lyrics ? $lyrics : false;
}

?>