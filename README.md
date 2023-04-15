# LyricsSync

## Description

## Dependencies
- [PHP 8.1](https://www.php.net)
- [Python 3.10](https://www.python.org)
- [Optionnal] [SpotifyAPI](https://github.com/Gerem66/SpotifyAPI)

## Usage
```php
<?php

require_once __DIR__ . '/lib/SpotifyAPI/spotifyapi.php';
require_once __DIR__ . '/lib/LyricalMind/lyricalmind.php';

$spotifyAPI = new SpotifyAPI(); // Optional if you want lyrics not synced
$lyricsMind = new LyricalMind($spotifyAPI);

$output = $lyricsMind->GetLyricsByID($id, $syncLyrics);
$output = $lyricsMind->GetLyricsByName($artist, $title, $syncLyrics);
?>
```

## How it works
Get lyrics
1. Get Spotify song ID if needed, bpm, key/mode, duration, ...
2. Get lyrics from scraping (AZ, Genius, P2C)

Synchronize lyrics (optional, need SpotifyAPI and AssemblyAI API key)
3. Download song from Spotify (spotdl)
4. Separate vocals from song (unmix)
5. Speech to text (AssemblyAI)
6. Syncronize lyrics (compare lyrics with speech to text)

## Configuration
* Create a configuration file named `config.json` with content:
```json
{
    "debug": false,
    "AssemblyAI_API_KEY": ""
}
```

## Status codes
- 0 => Success
- 1 => Spotify song not found
- 2 => Lyrics not found
- 3 => Song not downloaded
- 4 => Song not spleeted
- 5 => Speech to text failed
- 6 => Lyrics not synced

## Potentials lyrics sources
- [Music Story](https://developers.music-story.com/fr/developpeurs/lyric) ?
- [Lyrics.comm](https://lyrics.com) ?

## Packages
- [AssemblyAI](https://www.assemblyai.com)
- [Open Unmix](https://github.com/sigsep/open-unmix-pytorch)

## TODO (0/12)
- [ ] Test AssemblyAI PHP script
- [ ] Update "getlyrics"
    - [ ] Get lyrics from spotify
    - [ ] Add websites to scrap
- [x] Song
    - [x] Download from Spotify (spotdl)
    - [x] Spleeter from PHP (python3 spleeter)
- [x] Implement AssemblyAI
- [ ] Make database to store all data (prevent duplication requests)
- [ ] Remove python & unused scripts
- [ ] Rewrite properly & optimize
- [ ] Final tests
- [ ] Syncroniser les paroles ligne par ligne
- [ ] Syncroniser les paroles mot par mot