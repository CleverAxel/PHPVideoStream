<?php

namespace Bite;

class VideoStream
{
    private $CHUNK_SIZE = 1000 * 1000;

    //8 KiB, limite mis par fread si j'en crois la doc https://www.php.net/manual/en/function.fread.php
    private int $buffer = 8 * 1024;

    private int $start = 0;
    private int $end = 0;

    private int $fileSize = 0;
    private mixed $stream = null;
    private string $pathToFile;

    public function __construct(string $pathToFile)
    {
        $this->pathToFile = $pathToFile;
        $this->init();
    }

    public function tryOpenFile()
    {

        $this->stream = fopen($this->pathToFile, "rb");

        //Probalement un fichier non existent;
        if ($this->stream === false) {
            http_response_code(404);
            exit("Unable to open the file.");
        }

        $this->fileSize = filesize($this->pathToFile);
    }

    public function setHeaders()
    {
        $succesToClean = ob_clean();

        if (!$succesToClean) {
            ob_start();
        }
        header("Content-Type: video/mp4");
        header("Cache-Control: max-age=604800, public");
        header("Last-Modified: ".gmdate('D, d M Y H:i:s', filemtime($this->pathToFile)) . ' GMT' );
        header("Accept-Ranges: bytes");

        $this->parseRangeHeader();

        $contentLength = $this->end - $this->start + 1;
        fseek($this->stream, $this->start);
        http_response_code(206);
        header("Content-Length: " . $contentLength);
        header("Content-Range: bytes " . $this->start . "-" . $this->end . "/" . $this->fileSize);
    }

    private function parseRangeHeader()
    {
        $matches = [];
        $successToParse = preg_match("/bytes=(\d+)-(\d+)?/", $_SERVER['HTTP_RANGE'], $matches);

        if($successToParse !== 1){
            http_response_code(400);
            exit("Failed to parse the range header");
        }

        $this->start = (int)$matches[1];
        $this->end = isset($matches[2]) ? (int)$matches[2] : $this->start + $this->CHUNK_SIZE;

        // pourquoi $fileSize-1 ?
        // car quand on veut accéder au dernier élement d'un tableau c'est array[array.length-1]
        $this->end = min($this->fileSize-1, $this->end);
    }

    private function closeFile()
    {
        fclose($this->stream);
    }

    private function streamContent(){
        $filePointer = $this->start;
        while ($filePointer <= $this->end) {
            if (($filePointer + $this->buffer) > $this->end) {
                $this->buffer = $this->end - $filePointer + 1;
            }

            echo fread($this->stream, $this->buffer);
            ob_flush();
            flush();
            $filePointer += $this->buffer;
        }
    }

    private function init()
    {
        $this->tryOpenFile();
        $this->setHeaders();
        $this->streamContent();
        $this->closeFile();
    }
}

new VideoStream("video_test.mp4");
