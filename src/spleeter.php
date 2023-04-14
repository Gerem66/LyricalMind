<?php

/**
 * @param string $inputFile
 * @param string $outputFile
 * @param string $tempDirectory
 * @return boolean Success of Open unmix command
 */
function SeparateAudioFile($inputFile, $outputFile, $tempDirectory = '/tmp/umx/') {
    if (!str_ends_with($tempDirectory, '/')) $tempDirectory .= '/';
    if (file_exists($outputFile)) return true;
    if (!file_exists($inputFile)) return false;

    $pathSplit = explode('/', $inputFile);
    $id = explode('.', end($pathSplit))[0];
    $tempDirectoryID = $tempDirectory . $id . '/';

    // Open unmix
    $command = "umx --outdir \"{$tempDirectory}\" \"$inputFile\"";
    $status = bash($command, $output, false, true);
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