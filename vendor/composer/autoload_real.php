<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInitdf15f36f1b5371ef2880b77f3cfeffec
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        spl_autoload_register(array('ComposerAutoloaderInitdf15f36f1b5371ef2880b77f3cfeffec', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInitdf15f36f1b5371ef2880b77f3cfeffec', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInitdf15f36f1b5371ef2880b77f3cfeffec::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
