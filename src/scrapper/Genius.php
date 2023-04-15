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
    $artists = strtolower($artists);
    $artists = stripAccents($artists);
    $artists = str_replace(' ', '-', $artists);

    // Title rename - word1-word2-word3
    $title = strtolower($title);
    $title = stripAccents($title);
    $title = str_replace(' ', '-', $title);

    // Get Lyrics from Genius
    $url = "http://genius.com/$artists-$title-lyrics";
    $source = $url;

    $lyrics = RequestWithProxy($url, required: 'data-lyrics-container');
    if ($lyrics === false) return false;

    // Get lyrics from html
    file_put_contents('genius.html', $lyrics);
    $lyrics = explode('<br/>', $lyrics);
    $firstExploded = explode('>', $lyrics[0]);
    $lyrics[0] = end($firstExploded);
    $lyrics[count($lyrics) - 1] = explode('<', $lyrics[count($lyrics) - 1])[0];

    $lyrics = array_map('strip_tags', $lyrics);
    $lyrics = array_map('trim', $lyrics);
    $lyrics = array_map('html_entity_decode', $lyrics);
    $lyrics = array_map(fn($l) => (!str_starts_with($l, '[') && !str_ends_with($l, ']')) ? $l : '', $lyrics);
    $lyrics = implode("\n", $lyrics);
    return !!$lyrics ? $lyrics : false;
}

$lyrics = GetLyricsFromGenius('alkapote', 'plus haut');
print_r($lyrics);

?>