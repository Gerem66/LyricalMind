# LyricsSync

## Fonctionnement
- Get lyrics
    1. Audio
        1. Download from Spotify
        2. Spleet and keep voice
    2. Text
        1. Spotify
        2. Web scraping
        3. AI Speech recognition
- Synchronization of the lyrics
    1. AI Speech recognition
    2. Sync with lyrics

## Configuration
* Create a configuration file named `settings.json` with content:
```json
{
    "debug": false,
    "AssemblyAI_API_KEY": ""
}
```

## API
- [Music Story](https://developers.music-story.com/fr/developpeurs/lyric) ?
- [Lyrics.comm](https://lyrics.com) ?

## Packages
- [AssemblyAI](https://www.assemblyai.com)

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