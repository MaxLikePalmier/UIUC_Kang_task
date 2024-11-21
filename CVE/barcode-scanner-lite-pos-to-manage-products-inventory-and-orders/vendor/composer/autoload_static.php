<?php


namespace Composer\Autoload;

class ComposerStaticbusinessInit3304f3c18be79b03b679c83936601432
{
    public static $prefixLengthsPsr4 = array (
        'U' => 
        array (
            'UkrSolution\\BarcodeScanner\\' => 27,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'UkrSolution\\BarcodeScanner\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticbusinessInit3304f3c18be79b03b679c83936601432::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticbusinessInit3304f3c18be79b03b679c83936601432::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticbusinessInit3304f3c18be79b03b679c83936601432::$classMap;

        }, null, ClassLoader::class);
    }
}
