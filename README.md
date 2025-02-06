# LyricalMind - v0.1.0

Retrieves information and synchronized lyrics for a sound by name.

## Usage

```php
<?php
require_once __DIR__ . '/lib/SpotifyAPI/spotifyapi.php';
require_once __DIR__ . '/lib/LyricalMind/lyricalmind.php';

$spotifyAPI = new SpotifyAPI(); // Optional if you want lyrics not synced
$lyricsMind = new LyricalMind($spotifyAPI);

$syncLyrics = true;
$output = $lyricsMind->GetLyricsByID($id, $syncLyrics);
$output = $lyricsMind->GetLyricsByName("ARTIST", "TITLE", $syncLyrics);
?>
```


## How it works

### Get lyrics

1. Get Spotify song ID if needed, bpm, key/mode and duration
2. Get lyrics from scraping (AZ, Genius, P2C)

### Synchronize lyrics (optional, need SpotifyAPI and WhisperX)

3. Download song from Spotify (spotdl)
4. Separate vocals from song (unmix)
5. Speech to text (WhisperX)
6. Syncronize lyrics (compare lyrics with speech to text)

## Configuration

* Create a configuration file named `config.json` with content:
```json
{
    "debug": false
}
```

Go to [SpotifyAPI](https://www.github.com/Gerem66/SpotifyAPI) to setup SpotifyAPI


## Status codes

| Code | Description |
|---|---|
| 0 | Success |
| 1 | Spotify song not found |
| 2 | Lyrics not found |
| 3 | Song not downloaded |
| 4 | Song not spleeted |
| 5 | Speech to text failed |
| 6 | Lyrics not synced (not enough words found) |


## Dependencies
- [PHP 8.1](https://www.php.net)
* Optional, needed for lyrics sync:
    - [SpotifyAPI](https://github.com/Gerem66/SpotifyAPI)
    - [WhisperX](https://github.com/m-bain/whisperX)

## Packages
* This project use:
    - [SpotifyAPI](https://developer.spotify.com)
    - [WhisperX](https://github.com/m-bain/whisperX)
    - [Open Unmix](https://github.com/sigsep/open-unmix-pytorch)
* Scraping:
    - [AZ Lyrics](https://www.azlyrics.com)
    - [Genius](https://genius.com)
    - [Paroles2Chansons](https://www.paroles2chansons.com)


## TODO
- [x] Noter les signatures rythmique (spotifyAPI)
- [x] DD/DD_db: Faire un système de logs avancé
- [ ] GetLyrics: Acheter une base de données de paroles
- [ ] Algo: Affiner les déductions avec les fichier audio
- [ ] Algo: Syncroniser les paroles mot par mot


## Potentials lyrics sources

- [Musixmatch](https://www.musixmatch.com)
- [Lyricfind](https://www.lyricfind.com)
- [Music Story](https://developers.music-story.com/fr/developpeurs/lyric)
- [Lyrics.com](https://lyrics.com)
