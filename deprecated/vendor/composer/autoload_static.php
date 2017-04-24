<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit79cf16c8fe0f2576e19bcbbaead2273d
{
    public static $prefixLengthsPsr4 = array (
        'U' => 
        array (
            'URL\\' => 4,
        ),
        'I' => 
        array (
            'IronBound\\WP_Notifications\\' => 27,
            'IronBound\\DB\\' => 13,
            'IronBound\\DBLogger\\' => 19,
            'IronBound\\Cache\\' => 16,
            'ITELIC\\' => 7,
        ),
        'F' => 
        array (
            'Faker\\' => 6,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'URL\\' => 
        array (
            0 => __DIR__ . '/..' . '/glenscott/url-normalizer/src/URL',
        ),
        'IronBound\\WP_Notifications\\' => 
        array (
            0 => __DIR__ . '/..' . '/ironbound/wp-notifications/src',
        ),
        'IronBound\\DB\\' => 
        array (
            0 => __DIR__ . '/..' . '/ironbound/db/src',
        ),
        'IronBound\\DBLogger\\' => 
        array (
            0 => __DIR__ . '/..' . '/ironbound/db-logger/src',
        ),
        'IronBound\\Cache\\' => 
        array (
            0 => __DIR__ . '/..' . '/ironbound/cache/src',
        ),
        'ITELIC\\' => 
        array (
            0 => __DIR__ . '/../..' . '/lib',
        ),
        'Faker\\' => 
        array (
            0 => __DIR__ . '/..' . '/fzaninotto/faker/src/Faker',
        ),
    );

    public static $prefixesPsr0 = array (
        'P' => 
        array (
            'Psr\\Log\\' => 
            array (
                0 => __DIR__ . '/..' . '/psr/log',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit79cf16c8fe0f2576e19bcbbaead2273d::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit79cf16c8fe0f2576e19bcbbaead2273d::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit79cf16c8fe0f2576e19bcbbaead2273d::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}