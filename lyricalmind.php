<?php

/**
 * LyricalMind
 * Get lyrics of a song from scraping and can sync them with AssemblyAI
 * @author Gerem66 <contact@gerem.ca>
 * @version 0.0.1
 * @link https://github.com/Gerem66/LyricalMind
 */

//namespace LyricalMind;

use LyricalMind\AssemblyAIException;

require_once __DIR__ . '/src/bash.php';
require_once __DIR__ . '/src/utils.php';
require_once __DIR__ . '/src/lyrics.php';
require_once __DIR__ . '/src/scrapper.php';
require_once __DIR__ . '/src/exceptions.php';
require_once __DIR__ . '/lib/assemblyai.php';


class LyricalMind
{
    /** @var AssemblyAI|false $assemblyAI */
    private $assemblyAI = false;

    /** @var SpotifyAPI|false $spotifyAPI */
    private $spotifyAPI = false;

    private $configFile = __DIR__ . '/settings.json';
    private $tempVocalsPath = __DIR__ . '/tmp';

    /**
     * LyricalMind constructor.
     * @param SpotifyAPI|false $spotifyAPI SpotifyAPI class (false to disable)
     * @throws \AssemblyAI\AssemblyAIException If settings file not found
     */
    public function __construct($spotifyAPI = false) {
        if (!file_exists($this->configFile)) {
            throw new AssemblyAIException('Settings file not found');
        }

        // Define AssemblyAI
        $settings = json_decode(file_get_contents($this->configFile), true);
        $keyAssemblyAI = $settings['AssemblyAI_API_KEY'];
        if (isset($keyAssemblyAI)) {
            $this->assemblyAI = new AssemblyAI($keyAssemblyAI);
        }

        // Define SpotifyAPI
        if ($spotifyAPI !== false) {
            $this->spotifyAPI = $spotifyAPI;
        }
    }

    /**
     * Main script: return lyrics of a song, or false if not found
     * If syncLyrics is true, will try to sync lyrics found with AssemblyAI
     *     or return lyrics from speech recognition if lyrics are not found
     * @param string $artists
     * @param string $title
     * @param bool $syncLyrics Needs AssemblyAI API key & SpotifyAPI class
     * @return string|false
     */
    public function GetLyrics($artists, $title, $syncLyrics = false) {
        $output = array(
            'status' => 'success',
            'error' => false,
            'artists' => $artists,
            'title' => $title,
            'lyrics' => false,
            'timecodes' => false,
            'total_time' => 0
        );
        $startTime = microtime(true);

        // Check database
        // TODO

        // Scrapper
        $output['lyrics'] = scrapper($artists, $title, $output['lyricsSource']);
        if ($output['lyrics'] === false) {
            $output['status'] = 'error';
            $output['error'] = 'LyricsMind: Lyrics not found';
            $output['total_time'] = microtime(true) - $startTime;
            return json_encode($output);
        }

        if ($syncLyrics) {
            // Check SpotifyAPI class
            if (!class_exists('SpotifyAPI'))
                throw new AssemblyAIException('SpotifyAPI class not found');
            if ($this->spotifyAPI === false)
                throw new AssemblyAIException('SpotifyAPI class not defined');

            // Check AssemblyAI API
            if ($this->assemblyAI === false)
                throw new AssemblyAIException('AssemblyAI API key not defined');

            // Get ID for download
            $id = $this->spotifyAPI->GetIdByName($artists, $title);
            if ($id === false) {
                $output['status'] = 'error';
                $output['error'] = 'SpotifyAPI: Song ID not found';
                $output['total_time'] = microtime(true) - $startTime;
                return json_encode($output);
            }

            // Download audio (SpotifyAPI > spotdl)
            $downloaded = $this->spotifyAPI->Download($id, $this->tempVocalsPath);
            if ($downloaded === false) {
                $output['status'] = 'error';
                $output['error'] = 'SpotifyAPI: song not downloaded';
                $output['total_time'] = microtime(true) - $startTime;
                return json_encode($output);
            }

            // Spleet audio & save vocals (spleeter / ffmpeg)
            $filenameDownloaded = "{$id}.mp3";
            $filenameSpleeted = "{$this->tempVocalsPath}/{$id}_vocals.mp3";
            $spleeted = SPLEETER_separateAudioFile($filenameDownloaded, $filenameSpleeted);
            if ($spleeted === false) {
                $output['status'] = 'error';
                $output['error'] = 'Spleeter: vocals not separated';
                $output['total_time'] = microtime(true) - $startTime;
                return json_encode($output);
            }

            // Get timecodes from audio reference
            $audio_url = $this->assemblyAI->UploadFile($filenameSpleeted);
            $transcriptID = $this->assemblyAI->SubmitAudioFile($audio_url, 'en_us');
            list($result, $error) = $this->assemblyAI->GetTranscript($transcriptID);
            if ($result === false || $error !== false) {
                echo("Error transcribing file: $error");
                return false;
            }
            $referenceWords = $result['words']; // Or 'text'

            // Speech recognition on vocals (AssemblyAI)
            // Sync lyrics with speech recognition (php script)
            $lyrics = new Lyrics($output['lyrics']);
            $timecodes = $lyrics->SyncSongStructure($referenceWords);
            if ($timecodes === false || count($timecodes) ===0) {
                $output['status'] = 'error';
                $output['error'] = 'Lyrics: lyrics not synced';
                $output['total_time'] = microtime(true) - $startTime;
                return json_encode($output);
            }
            $output['timecodes'] = $timecodes;

            // Save lyrics in database ? (DataBase ?)
            // TODO
        }

        $output['total_time'] = microtime(true) - $startTime;
        return json_encode($output);
    }
}

?>