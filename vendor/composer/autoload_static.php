<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit2de33a7a3c8e072369d3af7c2137a7e2
{
    public static $prefixLengthsPsr4 = array (
        'N' => 
        array (
            'NCB01\\WDB\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'NCB01\\WDB\\' => 
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
            $loader->prefixLengthsPsr4 = ComposerStaticInit2de33a7a3c8e072369d3af7c2137a7e2::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit2de33a7a3c8e072369d3af7c2137a7e2::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit2de33a7a3c8e072369d3af7c2137a7e2::$classMap;

        }, null, ClassLoader::class);
    }
}