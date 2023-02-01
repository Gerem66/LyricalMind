<?php

    /**
     * @param string $command Shell command
     * @param array $output Output of command
     * @return integer Status of command
     */
    function bash($command, &$output = null, $user = 'root') {
        $command = "sudo -u $user $command";
        $command = escapeshellcmd($command);
        exec($command, $output, $status);
        return $status;
    }

    /**
     * Compress audio file to 320kbps to output or /tmp/randomstring-inputfile
     * @param string $inputFile
     * @param string $outputFile Outputfile name (optional, if null, will be the same as inputfile)
     * @return boolean Success of FFMPEG command
     */
    function FFMPEG_reduceAudioFile($inputFile, $outputFile = null) {
        if ($outputFile === null) {
            $outputFile = '/tmp/' . RandomString(10) . '-' . $inputFile;
            $command = "ffmpeg -i \"$inputFile\" -acodec libmp3lame -ac 2 -ab 320k -ar 44100 \"$outputFile\"";
            $status = bash($command);
            if ($status !== 0) {
                return false;
            }

            // Delete inputfile and rename outputfile to inputfile
            $command = "rm \"$inputFile\" && mv \"$outputFile\" \"$inputFile\"";
            $status = bash($command);
            return $status === 0;
        }

        $command = "ffmpeg -i \"$inputFile\" -acodec libmp3lame -ac 2 -ab 320k -ar 44100 \"$outputFile\"";
        $status = bash($command, $output);
        return $status === 0;
    }

    /**
     * @param string $inputFile
     * @param string $outputFile
     * @param string $tempDirectory
     * @return boolean Success of Spleeter command
     */
    function SPLEETER_separateAudioFile($inputFile, $outputFile, $tempDirectory = '/tmp/spleeter/') {
        if (!str_ends_with($tempDirectory, '/')) $tempDirectory .= '/';
        $id = explode('.', (end(explode('/', $inputFile))))[0];
        $tempDirectoryID = $tempDirectory . $id . '/';

        // Spleeter
        $command = "python3 -m spleeter separate \"$inputFile\" -p spleeter:2stems -o \"{$tempDirectory}\"";
        $status = bash($command, $output);
        if ($status !== 0) return false;

        // Move vocal file (with FFMPEG to 320kbps)
        $moved = FFMPEG_reduceAudioFile($tempDirectoryID . 'vocals.wav', $outputFile);
        if ($moved === false) return false;

        // Remove temp files
        $command = "rm -rf \"$tempDirectoryID\"";
        $status = bash($command, $output);
        if ($status !== 0) return false;

        return true;
    }

?>
