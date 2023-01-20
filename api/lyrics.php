<?php

    // This script will be used as an API to get lyrics from the LyricsAPI

    /**
     * GetLyrics
     * @api {get} /api/lyrics.php?&artists=artist&title=title&lyricsSync=1 Get Lyrics
     */

    // Get the parameters
    $artists = $_GET['artists'];
    $title = $_GET['title'];
    $lyricsSync = isset($_GET['lyricsSync']);

    // Check the parameters
    if (!isset($artists, $title)) {
        exit();
    }

    $output = array('status' => 'ok');

    // Get the lyrics
    $lyrics = GetLyrics($artists, $title);

    // Lyrics not found
    if ($lyrics === false) {
        $output['status'] = 'error';
        $output['message'] = 'Lyrics not found';
        exit(json_encode($output));
    }

    // Lyrics found
    $output['lyrics'] = $lyrics;

    // Sync the lyrics
    if ($lyricsSync) {
        $syncStatus = bash('python3 ' . __DIR__ . '/lyrics-sync.py ' . escapeshellarg($lyrics), $timecodes);

        // Sync error
        if ($syncStatus !== 0) {
            $output['status'] = 'error';
            $output['message'] = 'Lyrics not synced';
            exit(json_encode($output));
        }

        // Sync success
        $output['timecodes'] = $timecodes;
    }

    // Return the lyrics
    $json = json_encode($output);
    if ($json === false) exit();
    echo($json);

?>