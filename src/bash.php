<?php

    /**
     * @param string $command Shell command
     * @param array $output Output of command
     * @param string|false $user User to run command as (false to run as default user)
     * @return integer Status of command
     */
    function bash($command, &$output = null, $user = false) {
        if ($user !== false)
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
            $command = "ffmpeg -i \"$inputFile\" -acodec libmp3lame -ac 2 -ab 320k -ar 44100 \"$outputFile\" -hide_banner -loglevel quiet";
            $status = bash($command);
            if ($status !== 0) return false;

            // Delete inputfile and rename outputfile to inputfile
            $command = "rm \"$inputFile\" && mv \"$outputFile\" \"$inputFile\"";
            $status = bash($command);
            return $status === 0;
        }

        $command = "ffmpeg -i \"$inputFile\" -acodec libmp3lame -ac 2 -ab 320k -ar 44100 \"$outputFile\" -hide_banner -loglevel quiet";
        $status = bash($command, $output);
        return $status === 0;
    }

    /**
     * Cut audio file to output and hide FFMPEG output
     * @param string $inputFile
     * @param string $outputFile
     * @param int $start Start time
     * @param int $duration Duration
     * @return boolean Success of FFMPEG command
     */
    function FFMPEG_cutAudioFile($inputFile, $outputFile, $start, $duration) {
        $command = "ffmpeg -i \"$inputFile\" -acodec copy -ss $start -t $duration \"$outputFile\" -y -hide_banner -loglevel quiet";
        $status = bash($command);
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
        if (file_exists($outputFile)) return true;

        $pathSplit = explode('/', $inputFile);
        $id = explode('.', end($pathSplit))[0];
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
