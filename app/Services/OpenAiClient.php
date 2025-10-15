<?php

namespace App\Services;

use RuntimeException;

class OpenAiClient
{
    /**
     * Send a JSON request to an OpenAI compatible endpoint.
     *
     * @param string $apiKey
     * @param string $endpoint
     * @param array<string,mixed> $payload
     * @param array<int,string> $extraHeaders
     * @return array<string,mixed>
     */
    public static function postJson($apiKey, $endpoint, array $payload, array $extraHeaders = array())
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL uzantısı etkin değil.');
        }

        $ch = curl_init($endpoint);
        if ($ch === false) {
            throw new RuntimeException('OpenAI uç noktasına bağlanılamadı.');
        }

        $headers = array_merge(
            array(
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ),
            $extraHeaders
        );

        $body = json_encode($payload);
        if ($body === false) {
            throw new RuntimeException('İstek yükü JSON formatına dönüştürülemedi.');
        }

        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 60,
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('OpenAI isteği başarısız oldu: ' . $error);
        }

        curl_close($ch);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('OpenAI yanıtı çözümlenemedi: ' . substr($response, 0, 200));
        }

        if ($httpCode >= 400) {
            $message = self::extractErrorMessage($decoded);
            throw new RuntimeException('OpenAI isteği ' . $httpCode . ' kodu ile reddedildi: ' . $message);
        }

        return $decoded;
    }

    /**
     * Send a multipart request, typically for image edits.
     *
     * @param string $apiKey
     * @param string $endpoint
     * @param array<string,mixed> $fields
     * @param array<string,\CURLFile> $files
     * @return array<string,mixed>
     */
    public static function postMultipart($apiKey, $endpoint, array $fields, array $files)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL uzantısı etkin değil.');
        }

        $ch = curl_init($endpoint);
        if ($ch === false) {
            throw new RuntimeException('OpenAI uç noktasına bağlanılamadı.');
        }

        $payload = array();
        foreach ($fields as $key => $value) {
            $payload[$key] = $value;
        }
        foreach ($files as $key => $file) {
            $payload[$key] = $file;
        }

        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $apiKey,
            ),
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 120,
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('OpenAI isteği başarısız oldu: ' . $error);
        }

        curl_close($ch);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('OpenAI yanıtı çözümlenemedi: ' . substr($response, 0, 200));
        }

        if ($httpCode >= 400) {
            $message = self::extractErrorMessage($decoded);
            throw new RuntimeException('OpenAI isteği ' . $httpCode . ' kodu ile reddedildi: ' . $message);
        }

        return $decoded;
    }

    /**
     * Request a standard text completion/chat completion style response.
     *
     * @param string $apiKey
     * @param string $endpoint
     * @param array<string,mixed> $payload
     * @return string
     */
    public static function postJsonForContent($apiKey, $endpoint, array $payload)
    {
        $response = self::postJson($apiKey, $endpoint, $payload);

        if (isset($response['choices'][0]['message']['content'])) {
            return (string)$response['choices'][0]['message']['content'];
        }

        if (isset($response['choices'][0]['text'])) {
            return (string)$response['choices'][0]['text'];
        }

        throw new RuntimeException('OpenAI içeriği boş yanıtladı.');
    }

    /**
     * Request an image generation.
     *
     * @param string $apiKey
     * @param string $model
     * @param string $prompt
     * @param string $size
     * @return array<string,mixed>
     */
    public static function requestImageGeneration($apiKey, $model, $prompt, $size)
    {
        $payload = array(
            'model' => $model,
            'prompt' => $prompt,
            'size' => $size,
            'response_format' => 'b64_json',
        );

        if ($model === 'dall-e-3') {
            $payload['quality'] = 'standard';
        }

        return self::postJson($apiKey, 'https://api.openai.com/v1/images/generations', $payload);
    }

    /**
     * Request an image edit using an uploaded template.
     *
     * @param string $apiKey
     * @param string $model
     * @param string $prompt
     * @param string $size
     * @param string $templatePath
     * @param string|null $maskPath
     * @return array<string,mixed>
     */
    public static function requestImageEdit($apiKey, $model, $prompt, $size, $templatePath, $maskPath = null)
    {
        $fields = array(
            'model' => $model,
            'prompt' => $prompt,
            'size' => $size,
            'response_format' => 'b64_json',
        );

        $files = array(
            'image' => new \CURLFile($templatePath, 'image/png', basename($templatePath)),
        );

        if ($maskPath && file_exists($maskPath)) {
            $files['mask'] = new \CURLFile($maskPath, 'image/png', basename($maskPath));
        }

        return self::postMultipart($apiKey, 'https://api.openai.com/v1/images/edits', $fields, $files);
    }

    /**
     * Fetch billing credits from OpenAI dashboard API.
     *
     * @param string $apiKey
     * @return array<string,mixed>
     */
    public static function fetchBillingCredits($apiKey)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL uzantısı etkin değil.');
        }

        $endpoint = 'https://api.openai.com/v1/dashboard/billing/credit_grants';
        $ch = curl_init($endpoint);
        if ($ch === false) {
            throw new RuntimeException('OpenAI faturalandırma API çağrısı başlatılamadı.');
        }

        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $apiKey,
            ),
            CURLOPT_TIMEOUT => 15,
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('OpenAI faturalandırma API isteği başarısız oldu: ' . $error);
        }

        curl_close($ch);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('OpenAI faturalandırma yanıtı çözümlenemedi.');
        }

        if ($httpCode >= 400) {
            $message = self::extractErrorMessage($decoded);
            throw new RuntimeException('OpenAI faturalandırma isteği ' . $httpCode . ' kodu ile reddedildi: ' . $message);
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $response
     * @return string
     */
    private static function extractErrorMessage(array $response)
    {
        if (isset($response['error']['message'])) {
            return (string)$response['error']['message'];
        }

        return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
