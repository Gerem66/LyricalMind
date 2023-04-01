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
require_once __DIR__ . '/src/proxy.php';
require_once __DIR__ . '/src/output.php';
require_once __DIR__ . '/src/lyrics.php';
require_once __DIR__ . '/src/scrapper.php';
require_once __DIR__ . '/src/spleeter.php';
require_once __DIR__ . '/src/exceptions.php';
require_once __DIR__ . '/api/assemblyai.php';


class LyricalMind
{
    /** @var AssemblyAI|false $assemblyAI */
    private $assemblyAI = false;

    /** @var SpotifyAPI|false $spotifyAPI */
    private $spotifyAPI = false;

    private $configFile = __DIR__ . '/config.json';
    private $tempVocalsPath = __DIR__ . '/tmp';

    /**
     * LyricalMind constructor.
     * @param SpotifyAPI|false $spotifyAPI SpotifyAPI class (false to disable)
     * @throws \AssemblyAI\AssemblyAIException If settings file not found
     */
    public function __construct($spotifyAPI = false, $tempVocalsPath = false) {
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

        // Create temp vocals folder
        if ($tempVocalsPath !== false) {
            $this->tempVocalsPath = $tempVocalsPath;
            if (str_ends_with($this->tempVocalsPath, '/')) {
                $this->tempVocalsPath = substr($this->tempVocalsPath, 0, strlen($this->tempVocalsPath) - 1);
            }
        }
        if (!file_exists($this->tempVocalsPath)) {
            bash('mkdir ' . $this->tempVocalsPath);
        }
    }

    public function RemoveTempFiles($id) {
        if ($id === false) return;
        $filepath = "{$this->tempVocalsPath}/{$id}.mp3";
        $filepath_vocals = "{$this->tempVocalsPath}/{$id}_vocals.mp3";
        if (file_exists($filepath)) bash('rm -rf ' . $filepath);
        if (file_exists($filepath_vocals)) bash('rm -rf ' . $filepath_vocals);
    }

    /**
     * Main script: return lyrics of a song, or false if not found
     * If syncLyrics is true, will try to sync lyrics found with AssemblyAI
     *     or return lyrics from speech recognition if lyrics are not found
     * @param string $artists
     * @param string $title
     * @param bool $syncLyrics Needs AssemblyAI API key & SpotifyAPI class
     * @return LyricalMindOutput
     */
    public function GetLyricsByName($artists, $title, $syncLyrics = false) {
        $output = new LyricalMindOutput();
        $output->artists = explode(',', $artists);
        $output->title = $title;

        // Get spotidy ID for download
        $spotifyID = $this->spotifyAPI->GetTrackIdByName($artists, $title);
        if ($spotifyID === false) {
            return $output->SetStatus('error', 1, 'SpotifyAPI: Song ID not found');
        }

        return $this->GetLyricsByID($spotifyID, $syncLyrics);
    }

    public function GetLyricsByID($spotifyID, $syncLyrics = false) {
        $output = new LyricalMindOutput();

        // Get song info
        $infoSuccess = $this->GetSongInfo($output, $spotifyID);
        if ($infoSuccess === false) {
            return $output;
        }

        // Scrapper
        $lyricsRaw = scrapper($output->artists[0], $output->title, $output->lyrics_source);
        if ($lyricsRaw === false) {
            return $output->SetStatus('error', 2, 'LyricsMind: Lyrics not found');
        }

        $lyrics = new Lyrics($lyricsRaw);
        $output->lyrics = $lyrics->GetVerses();

        if ($syncLyrics) {
            $this->SyncLyrics($output, $lyrics);
        }

        return $output;
    }

    /**
     * Get song info (bpm, key & mode)
     * @param LyricalMindOutput $output
     * @param string $spotifyID
     * @return bool True if success
     */
    private function GetSongInfo(&$output, $spotifyID) {
        if (count($output->artists) === 0 || $output->title === false) {
            $song = $this->spotifyAPI->GetTrack($spotifyID, $http_status);
            if ($http_status !== 200) {
                $output->SetStatus('error', 1, 'SpotifyAPI: GetTrack failed');
                return false;
            }
            $artists = array_filter($song['artists'], fn($artist) => $artist['type'] === 'artist');
            $artists = array_map(fn($artist) => $artist['name'], $artists);
            $output->artists = $artists;
            $output->title = $song['name'];

        }

        $song = $this->spotifyAPI->GetAudioFeature([$spotifyID]);
        if ($song === false || count($song) !== 1) {
            $output->SetStatus('error', 1, 'SpotifyAPI: GetAudioFeature failed');
            return false;
        }

        $output->id = $spotifyID;
        $output->bpm = $song[0]['tempo'];
        $output->key = $song[0]['key'];
        $output->mode = $song[0]['mode'];
        return true;
    }

    /**
     * Sync lyrics with AssemblyAI
     * @param LyricalMindOutput $output
     * @param Lyrics $lyrics
     * @return LyricalMindOutput
     */
    private function SyncLyrics(&$output, $lyrics) {
        // Check SpotifyAPI class
        if (!class_exists('SpotifyAPI'))
            throw new AssemblyAIException('SpotifyAPI class not found');
        if ($this->spotifyAPI === false)
            throw new AssemblyAIException('SpotifyAPI class not defined');

        // Check AssemblyAI API
        if ($this->assemblyAI === false)
            throw new AssemblyAIException('AssemblyAI API key not defined');

        // Download audio (SpotifyAPI > spotdl)
        $downloaded = $this->spotifyAPI->DownloadTrack($output->id, $this->tempVocalsPath);
        if ($downloaded === false) {
            return $output->SetStatus('error', 3, 'SpotifyAPI: song not downloaded');
        }

        // Spleet audio & save vocals (spleeter / ffmpeg)
        $filenameDownloaded = "{$this->tempVocalsPath}/{$output->id}.mp3";
        $filenameSpleeted = "{$this->tempVocalsPath}/{$output->id}_vocals.mp3";
        $spleeted = SPLEETER_separateAudioFile($filenameDownloaded, $filenameSpleeted);
        if ($spleeted === false) {
            return $output->SetStatus('error', 4, 'Spleeter: vocals not separated');
        }
        $output->voice_source = $filenameSpleeted;

        // Get timecodes from audio reference
        $audio_url = $this->assemblyAI->UploadFile($filenameSpleeted);
        $transcriptID = $this->assemblyAI->SubmitAudioFile($audio_url, 'en_us');
        $result = $this->assemblyAI->WaitTranscript($transcriptID, $error);
        if ($result === false || $error !== null) {
            return $output->SetStatus('error', 5, "AssemblyAI: error transcribing file ($error)");
        }
        $referenceWords = $result;

        // Speech recognition on vocals (AssemblyAI)
        // Sync lyrics with speech recognition (php script)
        $syncError = false;
        $timecodes = $lyrics->SyncStructure($referenceWords, $syncError);
        if ($timecodes === false || $syncError !== false) {
            return $output->SetStatus('error', 6, "LyricalMind: lyrics not synced ($syncError)");
        }
        $output->timecodes = $timecodes;
    }
}

?>