<?php

namespace UkrSolution\BarcodeScanner\API\classes;

class Checker
{
    static private $isMediaLoaded = false;

    static public function setMediaLoader()
    {
        self::$isMediaLoaded = true;
    }

    static public function getMediaLoader()
    {
        return self::$isMediaLoaded;
    }
}
