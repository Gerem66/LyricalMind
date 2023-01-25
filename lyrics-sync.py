# -*- coding: utf-8 -*-
#!/usr/bin/env python3

from lib.assemblyai import AssemblyAI_Upload, AssemblyAI_SpeechToText



filename = 'juice-voice.wav'

audio_url = AssemblyAI_Upload(filename)
if audio_url is None:
    print('Error uploading file')
    exit(1)

result, error = AssemblyAI_SpeechToText(audio_url)
if error is not None:
    print('Error transcribing file: {}'.format(error))
    exit(2)

print('Filename: {}\nAudio URL: {}\nResult: {}'.format(filename, audio_url, result))
