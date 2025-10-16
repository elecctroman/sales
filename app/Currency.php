<?php

namespace App;

class Currency
{
    /**
     * @var array<string,float>
     */
    private static $rateCache = array();

    /**
     * @param float $amount
     * @param string $from
     * @param string $to
     * @return float
     */
    public static function convert($amount, $from = 'TRY', $to = 'TRY')
    {
        return (float)$amount;
    }

    /**
     * @param float $amount
     * @param string $currency
     * @return string
     */
    public static function format($amount, $currency = 'TRY')
    {
        $formatted = number_format((float)$amount, 2, ',', '.');
        return self::symbol($currency) . $formatted;
    }

    /**
     * @param string $currency
     * @return string
     */
    public static function symbol($currency)
    {
        return '₺';
    }

    /**
     * @param string $from
     * @param string $to
     * @return float
     */
    public static function getRate($from, $to)
    {
        return 1.0;
    }

    /**
     * Force refresh of a currency pair and persist the latest value.
     *
     * @param string $from
     * @param string $to
     * @return float
     */
    public static function refreshRate($from, $to)
    {
        return 1.0;
    }

    /**
     * @param string $from
     * @param string $to
     * @return float
     */
    private static function fetchRate($from, $to)
    {
        return 1.0;
    }

    /**
     * @param string $url
     * @return string|null
     */
    private static function httpGet($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ResellerPanelBot/1.0)');
            $result = curl_exec($ch);
            curl_close($ch);
            if ($result !== false) {
                return $result;
            }
        }

        if (ini_get('allow_url_fopen')) {
            $context = stream_context_create(array(
                'http' => array(
                    'timeout' => 10,
                    'header' => "User-Agent: Mozilla/5.0 (compatible; ResellerPanelBot/1.0)\r\n",
                ),
            ));
            $result = @file_get_contents($url, false, $context);
            if ($result !== false) {
                return $result;
            }
        }

        return null;
    }
}
