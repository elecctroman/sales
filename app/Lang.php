<?php

namespace App;

class Lang
{
    /**
     * @var string
     */
    private static $locale = 'tr';

    /**
     * Sistem başlangıcında varsayılan yereli ayarla.
     *
     * @return void
     */
    public static function boot()
    {
        self::$locale = 'tr';

        if (isset($_SESSION['locale']) && $_SESSION['locale'] !== 'tr') {
            $_SESSION['locale'] = 'tr';
        }
    }

    /**
     * Çoklu dil desteği kaldırıldığı için yerel değiştirme isteği görmezden gelinir.
     *
     * @param string $locale
     * @return void
     */
    public static function setLocale($locale)
    {
        self::$locale = 'tr';
        $_SESSION['locale'] = 'tr';
    }

    /**
     * @return string
     */
    public static function locale()
    {
        return 'tr';
    }

    /**
     * @return string
     */
    public static function htmlLocale()
    {
        return 'tr';
    }

    /**
     * @param string $key
     * @param string|null $default
     * @return string
     */
    public static function get($key, $default = null)
    {
        return $default !== null ? (string)$default : (string)$key;
    }

    /**
     * @param string $text
     * @param string|null $key
     * @return string
     */
    public static function line($text, $key = null)
    {
        return is_string($text) ? $text : (string)$key;
    }

    /**
     * @return array<int,string>
     */
    public static function availableLocales()
    {
        return array('tr');
    }

    /**
     * @return string
     */
    public static function defaultLocale()
    {
        return 'tr';
    }

    /**
     * @param string $buffer
     * @return string
     */
    public static function filterOutput($buffer)
    {
        return $buffer;
    }
}
