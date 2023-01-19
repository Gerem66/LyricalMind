# -*- coding: utf-8 -*-
#!/usr/bin/env python3

import os
import pydub
from difflib import SequenceMatcher

import pocketsphinx             # Audio recognition
from fuzzywuzzy import fuzz     # Text phonetic comparison

class LyricsSync:
    tempFolder = './tmp/'
    step = .5

    def __init__(self, filename: str, lyrics: list[str]):
        self.filename = filename
        self.lyrics = lyrics

        self.tempFolder += os.path.basename(filename) + '/'
        self.ClearTempFolder()
        if not os.path.exists(self.tempFolder):
            os.mkdir(self.tempFolder)

        self.audio = pydub.AudioSegment.from_file(filename, format='wav')
        self.data = {}

    def ClearTempFolder(self):
        if not os.path.exists(self.tempFolder): return

        for file in os.listdir(self.tempFolder):
            os.remove(self.tempFolder + file)
        os.rmdir(self.tempFolder)

    def SplitFile(self):
        audioLength = len(self.audio)
        for i in range(0, audioLength, int(self.step * 1000)):
            newBasename = 'audio_part_{}.wav'.format(round(i/1000, 2))
            newFilename = self.tempFolder + newBasename
            audio_part = self.audio[i:i+int(self.step * 1000)]
            audio_part.export(newFilename, format='wav')

    def Recognize(self):
        self.data = {}
        for file in sorted(os.listdir(self.tempFolder)):
            sphinx = pocketsphinx.AudioFile(audio_file=self.tempFolder + file)
            recognitionText = ''
            for reco in sphinx:
                recognitionText += str(reco)
            index = round(float(file.split('_')[-1].replace('.wav', '')), 1)
            self.data[index] = {}
            for line in self.lyrics:
                self.data[index][line] = LyricsSync.GetRatio(line, recognitionText)

    def GetRatio(reference, line):
        if len(line) + 5 > len(reference):
            line = line[:len(reference) + 4]
        ratio1 = fuzz.token_sort_ratio(reference, line)

        sequence = SequenceMatcher(a=reference, b=line).ratio()
        ratio2 = round(sequence * 100)

        return { 'ratio1': ratio1, 'ratio2': ratio2 }

    def Analyze(self):
        pass

    def Save(self):
        with open(self.tempFolder + 'data.txt', 'w') as f:
            # Write header
            f.write('Time,')
            f.write(','.join(self.lyrics))
            f.write('\n')

            # Write data
            for index in self.data.keys():
                f.write(str(index))
                for line in self.lyrics:
                    ratioText = '{}|{}'.format(self.data[index][line]['ratio1'], self.data[index][line]['ratio2'])
                    f.write(',' + ratioText)
                f.write('\n')

# Get lyrics from file
with open('lyrics.txt', 'r') as f:
    lines = f.readlines()
lines = [line.replace('\n', '').strip() for line in lines]
filename = './juice-voice.wav'

lyricsSync = LyricsSync(filename, lines)
lyricsSync.step = .2

lyricsSync.SplitFile()
lyricsSync.Recognize()
lyricsSync.Analyze()

#print(lyricsSync.data)
lyricsSync.Save()
