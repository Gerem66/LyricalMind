import requests
from time import sleep

CHUNK_SIZE = 5_242_880  # 5MB
API_KEY_ASSEMBLYAI = '536f5c793d804de6ba8c99674859867a'
upload_endpoint = 'https://api.assemblyai.com/v2/upload'
transcript_endpoint = 'https://api.assemblyai.com/v2/transcript'

headers_auth_only = {
    'authorization': API_KEY_ASSEMBLYAI
}
headers = {
    "authorization": API_KEY_ASSEMBLYAI,
    "content-type": "application/json"
}

def AssemblyAI_Upload(filename):
    def read_file(filename):
        with open(filename, 'rb') as f:
            while True:
                data = f.read(CHUNK_SIZE)
                if not data:
                    break
                yield data

    upload_response = requests.post(upload_endpoint, headers=headers_auth_only, data=read_file(filename))
    if upload_response.status_code != 200:
        return None
    return upload_response.json()['upload_url']

def __assemblyai_transcribe(audio_url):
    transcript_request = { 'audio_url': audio_url }
    transcript_response = requests.post(transcript_endpoint, json=transcript_request, headers=headers)
    if transcript_response.status_code != 200:
        return None
    return transcript_response.json()['id']

def __assemblyai_poll(transcript_id):
    polling_endpoint = transcript_endpoint + '/' + transcript_id
    polling_response = requests.get(polling_endpoint, headers=headers)
    return polling_response.json()

def AssemblyAI_SpeechToText(url):
    if url is None:
        return None, 'url is None'
    transcribe_id = __assemblyai_transcribe(url)
    if transcribe_id is None:
        return None, 'assemblyai transcribe failed'
    while True:
        data = __assemblyai_poll(transcribe_id)
        if data['status'] == 'completed':
            return data, None
        elif data['status'] == 'error':
            return data, data['error']
        sleep(5)
