<?php

    // This script will be used as an API to get lyrics from the LyricsAPI

    /**
     * GetLyrics
     * 
     * Parameters:
     * - artists: string
     * - title: string
     * - lyricsSync: boolean
     * Return:
     * - status: string(ok|error)
     * - message: string
     * - lyrics: array
     * - [timecodes: array]
     * - time: float
     * @api {get} /api/lyrics.php?&artists=artist&title=title&lyricsSync=1 Get Lyrics
     * @example curl -G http://142.4.218.111/MaxTrack/api/lyrics.php -d 'artists=josman' -d 'title=intro'
     */

    require_once(__DIR__ . '/../src/utils.php');
    require_once(__DIR__ . '/../src/lyrics-find.php');

    $DEBUG = false;

    // Get the parameters
    $artists = $_GET['artists'];
    $title = $_GET['title'];
    $lyricsSync = isset($_GET['lyricsSync']);

    // Check the parameters
    if (!isset($artists, $title)) {
        exit();
    }

    $mt_start = microtime(true);
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
    $output['lyrics'] = explode("\n", $lyrics);

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

    $mt_end = microtime(true);
    $output['time'] = $mt_end - $mt_start;

    // Return the lyrics
    $json = json_encode($output);
    if ($json === false) exit();

    if ($DEBUG) {
        header('Content-Type: text/plain');
        echo("Result:\n$json\n\n");
        echo("Lyrics:\n$lyrics\n\n");
        echo("Time: " . ($mt_end - $mt_start) . "s\n\n");
    } else {
        header('Content-Type: application/json');
        echo($json);
    }

?>