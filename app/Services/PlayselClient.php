<?php

namespace App\Services;

use RuntimeException;

class PlayselClient
{
    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $token;

    /**
     * @var string|null
     */
    private $regionCode;

    /**
     * @var int
     */
    private $timeout;

    /**
     * @param string      $baseUrl
     * @param string      $token
     * @param string|null $regionCode
     * @param int         $timeout
     */
    public function __construct($baseUrl, $token, $regionCode = null, $timeout = 20)
    {
        $baseUrl = trim((string)$baseUrl);
        if ($baseUrl === '') {
            throw new RuntimeException('Playsel API adresi bos olamaz.');
        }

        if (!preg_match('~^https?://~i', $baseUrl)) {
            throw new RuntimeException('Playsel API adresi http(s) ile baslamalidir.');
        }

        $token = trim((string)$token);
        if ($token === '') {
            throw new RuntimeException('Playsel API token degeri bos olamaz.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->regionCode = $regionCode !== null && $regionCode !== '' ? (string)$regionCode : null;
        $this->timeout = (int)$timeout;
    }

    /**
     * @param string $baseUrl
     * @param string $email
     * @param string $password
     * @return array<string,mixed>
     */
    public static function authenticate($baseUrl, $email, $password)
    {
        $baseUrl = rtrim(trim((string)$baseUrl), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('Playsel API adresi zorunludur.');
        }

        if (!preg_match('~^https?://~i', $baseUrl)) {
            $baseUrl = 'https://' . ltrim($baseUrl, '/');
        }

        $email = trim((string)$email);
        $password = (string)$password;

        if ($email === '' || $password === '') {
            throw new RuntimeException('Playsel giris e-postasi ve sifresi zorunludur.');
        }

        $payload = array(
            'email' => $email,
            'password' => $password,
        );

        $response = self::rawRequest($baseUrl, 'POST', '/Login/Customer/Api/Token', array(
            'json' => $payload,
        ));

        if (empty($response['success'])) {
            $message = isset($response['message']) ? (string)$response['message'] : 'Playsel token alinamadi.';
            throw new RuntimeException($message);
        }

        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new RuntimeException('Playsel token yaniti beklenen veriyi icermiyor.');
        }

        $token = isset($response['data']['token']) ? (string)$response['data']['token'] : '';
        if ($token === '') {
            throw new RuntimeException('Playsel token bilgisi yanit icinde bulunamadi.');
        }

        $expiresInHours = isset($response['data']['expiresInHours']) ? (int)$response['data']['expiresInHours'] : 0;
        $customerApiKey = isset($response['data']['customerApiKey']) ? (string)$response['data']['customerApiKey'] : '';

        return array(
            'token' => $token,
            'expires_in_hours' => $expiresInHours,
            'customer_api_key' => $customerApiKey,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function getCustomer()
    {
        $response = $this->request('GET', '/Customer/Get');
        if (empty($response['success'])) {
            $message = isset($response['message']) ? (string)$response['message'] : 'Playsel musteri bilgisi alinamadi.';
            throw new RuntimeException($message);
        }

        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new RuntimeException('Playsel musteri yaniti gecersiz formatta.');
        }

        return $response['data'];
    }

    /**
     * @param int  $page
     * @param int  $pageSize
     * @param bool $detailed
     * @param int|null $categoryId
     * @return array<int,array<string,mixed>>
     */
    public function listProducts($page = 1, $pageSize = 100, $detailed = true, $categoryId = null)
    {
        $query = array(
            'page' => max(1, (int)$page),
            'pageSize' => max(1, min(200, (int)$pageSize)),
        );

        if ($detailed) {
            $query['detailed'] = 'true';
        }

        if ($categoryId !== null && (int)$categoryId > 0) {
            $query['productCategoryID'] = (int)$categoryId;
        }

        $response = $this->request('POST', '/Products/List', array(
            'query' => $query,
        ));

        if (empty($response['success'])) {
            $message = isset($response['message']) ? (string)$response['message'] : 'Playsel urun listesi alinamadi.';
            throw new RuntimeException($message);
        }

        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();
        return array_values($data);
    }

    /**
     * @param string              $method
     * @param string              $path
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private function request($method, $path, array $options = array())
    {
        $headers = array(
            'Authorization: ' . $this->token,
            'Accept: application/json',
        );

        if ($this->regionCode !== null) {
            $headers[] = 'h-region-code: ' . $this->regionCode;
        }

        $options['headers'] = isset($options['headers']) && is_array($options['headers'])
            ? array_merge($options['headers'], $headers)
            : $headers;

        $options['timeout'] = $this->timeout;

        return self::rawRequest($this->baseUrl, $method, $path, $options);
    }

    /**
     * @param string              $baseUrl
     * @param string              $method
     * @param string              $path
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function rawRequest($baseUrl, $method, $path, array $options = array())
    {
        $method = strtoupper($method);
        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        $query = isset($options['query']) && is_array($options['query']) ? $options['query'] : array();
        if ($query) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }

        $headers = isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : array();
        $headers[] = 'User-Agent: PlayselClient/1.0 (+https://playsel.com)';

        $body = null;
        if (isset($options['json'])) {
            $encoded = json_encode($options['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                throw new RuntimeException('Playsel istegi hazirlanamadi.');
            }
            $body = $encoded;
            $headers[] = 'Content-Type: application/json';
        } elseif (isset($options['body']) && $options['body'] !== null) {
            $body = (string)$options['body'];
        }

        $timeout = isset($options['timeout']) ? (int)$options['timeout'] : 20;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $curlOptions = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CUSTOMREQUEST => $method,
            );

            if ($method === 'POST' && $body === null && empty($options['json'])) {
                $curlOptions[CURLOPT_POST] = true;
            }

            if ($body !== null) {
                $curlOptions[CURLOPT_POSTFIELDS] = $body;
            }

            curl_setopt_array($ch, $curlOptions);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                throw new RuntimeException('Playsel istegi basarisiz: ' . $error);
            }

            return self::decodeResponse((string)$response, (int)$status);
        }

        $context = stream_context_create(array(
            'http' => array(
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body !== null ? $body : '',
                'timeout' => $timeout,
                'ignore_errors' => true,
            ),
        ));

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $message = 'Playsel istegi yanit vermedi.';
            if (isset($http_response_header) && is_array($http_response_header)) {
                $message .= ' HTTP: ' . implode(' ', $http_response_header);
            }
            throw new RuntimeException($message);
        }

        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d{3})/', $headerLine, $matches)) {
                    $status = (int)$matches[1];
                    break;
                }
            }
        }

        return self::decodeResponse((string)$response, $status);
    }

    /**
     * @param string $body
     * @param int    $status
     * @return array<string,mixed>
     */
    private static function decodeResponse($body, $status)
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $preview = trim($body);
            if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                if (mb_strlen($preview, 'UTF-8') > 200) {
                    $preview = mb_substr($preview, 0, 200, 'UTF-8') . '...';
                }
            } elseif (strlen($preview) > 200) {
                $preview = substr($preview, 0, 200) . '...';
            }

            if ($status > 0) {
                throw new RuntimeException(sprintf('Playsel JSON yaniti okunamadi (HTTP %d). Ornek: %s', $status, $preview));
            }

            throw new RuntimeException(sprintf('Playsel JSON yaniti okunamadi. Ornek: %s', $preview));
        }

        if ($status >= 400) {
            $message = isset($decoded['message']) ? (string)$decoded['message'] : 'Playsel hata durum kodu dondurdu: ' . $status;
            throw new RuntimeException($message);
        }

        return $decoded;
    }
}
