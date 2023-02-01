<?php

    class AssemblyAI {
        private string $API_KEY = '';

        public function __construct($API_KEY) {
            $this->API_KEY = $API_KEY;
        }

        public function SubmitAudioFile() {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.assemblyai.com/v2/transcript',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode([ 'audio_url' => 'https://bit.ly/3yxKEIY' ]),
                CURLOPT_HTTPHEADER => [ "authorization: {$this->API_KEY}" ],
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                echo('cURL Error #:' . $err);
                return false;
            }
            return $response;
        }

        public function GetTranscript($transcript_id) {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.assemblyai.com/v2/transcript/$transcript_id",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [ "authorization: {$this->API_KEY}" ],
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                echo('cURL Error #:' . $err);
                return false;
            }
            return $response;
        }
    }

?>