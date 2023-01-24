# -*- coding: utf-8 -*-
#!/usr/bin/env python3

import os
import pydub
from difflib import SequenceMatcher

import pocketsphinx             # Audio recognition
from fuzzywuzzy import fuzz     # Text phonetic comparison

# I still... 23s
# Can't take... 26s
# It's to the... 29s
# And I cannot... 32s
# Easier said... 35s

class LyricsSync:
    tempFolder = '../tmp/'
    step = .5

    def __init__(self, filename: str = None, lyrics: list[str] = ''):
        self.filename = filename
        self.lyrics = lyrics

        self.data = {}

        if filename is None:
            return

        self.tempFolder += os.path.basename(filename) + '/'
        self.ClearTempFolder()
        if not os.path.exists(self.tempFolder):
            os.mkdir(self.tempFolder)

        if not os.path.exists(filename) or not filename.endswith('.wav'):
            print('Audio file not found!')
            return

        self.audio = pydub.AudioSegment.from_file(filename, format='wav')

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
            i = float(file.split('_')[-1].replace('.wav', ''))
            index = '{:06.2f}'.format(i)
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
        f = open(self.tempFolder + 'data1.txt', 'w')
        g = open(self.tempFolder + 'data2.txt', 'w')

        # Write header
        f.write('Time,' + ','.join(self.lyrics) + '\n')
        g.write('Time,' + ','.join(self.lyrics) + '\n')

        # Write data
        i = 0
        while True:
            index = '{:06.2f}'.format(i)
            if index not in self.data.keys(): break

            f.write(index)
            g.write(index)
            for line in self.lyrics:
                f.write(',' + str(self.data[index][line]['ratio1']))
                g.write(',' + str(self.data[index][line]['ratio2']))
            f.write('\n')
            g.write('\n')

            i += self.step

        f.close()
        g.close()

    def Load(self, datafile: str):
        f = open(datafile, 'r')
        lines = [ line.replace('\n', '') for line in f.readlines() ]
        f.close()

        self.data = {}
        self.lyrics = lines[0].split(',')[1:]

        for line in lines[1:]:
            line = line.split(',')
            index = '{:06.2f}'.format(float(line[0]))
            self.data[index] = {}
            for i in range(1, len(line)):
                self.data[index][self.lyrics[i-1]] = line[i]


load = False

if load:
    lyricsSync = LyricsSync()
    lyricsSync.Load('../tmp/juice-voice.wav/data1.txt')
    print(lyricsSync.data)
    exit()

# Get lyrics from file
with open('../lyrics.txt', 'r') as f: lines = f.readlines()
lines = [line.replace('\n', '').strip() for line in lines]
filename = '../juice-voice.wav'

lyricsSync = LyricsSync(filename, lines)
lyricsSync.step = .2

lyricsSync.SplitFile()
lyricsSync.Recognize()
lyricsSync.Analyze()

#print(lyricsSync.data)
lyricsSync.Save()
