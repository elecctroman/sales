<?php

namespace App\Services;

use App\Database;
use App\Settings;
use RuntimeException;

class DalleService
{
    /**
     * Default prompt template if admin has not defined one.
     *
     * @return string
     */
    public static function defaultPromptTemplate()
    {
        return 'Premium e-ticaret ürün görseli oluştur. Ürün adı: {{product_name}}. Marka kimliğini güçlendiren, yüksek çözünürlüklü, ışık dengesi yapılmış ve satış odaklı bir tanıtım görseli tasarla. Ürünün avantajlarını destekleyen minimal metin yer alabilir. Görsel arka planı modern ve temiz olsun.';
    }

    /**
     * Replace supported placeholders with product context.
     *
     * @param string $template
     * @param array<string,mixed> $product
     * @return string
     */
    public static function buildPrompt($template, array $product)
    {
        $replacements = array(
            '{{product_name}}' => isset($product['name']) ? (string)$product['name'] : '',
            '{{product_description}}' => isset($product['description']) ? (string)$product['description'] : '',
            '{{product_short_description}}' => isset($product['short_description']) ? (string)$product['short_description'] : '',
            '{{category_name}}' => isset($product['category_name']) ? (string)$product['category_name'] : '',
        );

        return strtr($template, $replacements);
    }

    /**
     * Estimate cost per image based on model and size.
     *
     * @param string $model
     * @param string $size
     * @param bool $usingTemplate
     * @return float
     */
    public static function estimateCost($model, $size, $usingTemplate = false)
    {
        $model = strtolower((string)$model);
        $size = strtolower((string)$size);

        $prices = array(
            'dall-e-3' => array(
                '1024x1024' => 0.08,
                '512x512' => 0.06,
                '256x256' => 0.04,
            ),
            'dall-e-2' => array(
                '1024x1024' => 0.02,
                '512x512' => 0.018,
                '256x256' => 0.016,
            ),
        );

        $base = isset($prices[$model][$size]) ? (float)$prices[$model][$size] : 0.08;

        if ($usingTemplate && $model === 'dall-e-2') {
            // Image edit requests are billed differently, apply a modest multiplier.
            $base *= 1.5;
        }

        return round($base, 3);
    }

    /**
     * Generate and store an image for a given product.
     *
     * @param array<string,mixed> $product
     * @param string|null $promptOverride
     * @return string Path to stored image relative to public root
     */
    public static function generateProductImage(array $product, $promptOverride = null)
    {
        $apiKey = Settings::get('integration_dalle_api_key');
        if (!$apiKey) {
            throw new RuntimeException('OpenAI API anahtarı tanımlı değil.');
        }

        $model = Settings::get('integration_dalle_model') ?: 'dall-e-3';
        $size = Settings::get('integration_dalle_size') ?: '1024x1024';
        $templateSetting = Settings::get('integration_dalle_template_path');
        $promptTemplate = $promptOverride ?: (Settings::get('integration_dalle_prompt') ?: self::defaultPromptTemplate());
        $prompt = self::buildPrompt($promptTemplate, $product);

        $response = null;
        $usingTemplate = false;

        if ($templateSetting) {
            $absoluteTemplate = self::resolvePublicPath($templateSetting);
            if (!is_file($absoluteTemplate)) {
                throw new RuntimeException('Yüklenen şablon bulunamadı: ' . $templateSetting);
            }

            $usingTemplate = true;
            // Şablonlu üretim sadece DALL-E 2 tarafından destekleniyor.
            $editModel = $model === 'dall-e-3' ? 'dall-e-2' : $model;
            $response = OpenAiClient::requestImageEdit($apiKey, $editModel, $prompt, $size, $absoluteTemplate);
        } else {
            $response = OpenAiClient::requestImageGeneration($apiKey, $model, $prompt, $size);
        }

        $imagePath = self::storeImageFromResponse($response, 'product-' . (isset($product['id']) ? (int)$product['id'] : time()));

        if ($imagePath === null) {
            throw new RuntimeException('DALL-E yanıtından görsel alınamadı.');
        }

        return $imagePath;
    }

    /**
     * Generate an image for general content like blog articles.
     *
     * @param string $prompt
     * @param string $prefix
     * @param string|null $size
     * @return string|null
     */
    public static function generateGenericImage($prompt, $prefix = 'content', $size = null)
    {
        $apiKey = Settings::get('integration_dalle_api_key');
        if (!$apiKey) {
            return null;
        }

        $model = Settings::get('integration_dalle_model') ?: 'dall-e-3';
        $size = $size ?: (Settings::get('integration_dalle_size') ?: '1024x1024');

        $response = OpenAiClient::requestImageGeneration($apiKey, $model, $prompt, $size);

        return self::storeImageFromResponse($response, $prefix . '-' . date('YmdHis'));
    }

    /**
     * Synchronise products without images by generating visuals in bulk.
     *
     * @param int $limit
     * @return array<string,mixed>
     */
    public static function syncMissingProductImages($limit = 5)
    {
        $apiKey = Settings::get('integration_dalle_api_key');
        if (!$apiKey) {
            throw new RuntimeException('OpenAI API anahtarı tanımlı değil.');
        }

        $pdo = Database::connection();
        $limit = max(1, (int)$limit);

        $stmt = $pdo->query('SELECT p.id, p.name, p.description, p.short_description, p.image_url, c.name AS category_name '
            . 'FROM products p LEFT JOIN categories c ON c.id = p.category_id '
            . 'WHERE (p.image_url IS NULL OR p.image_url = \'\') '
            . 'ORDER BY p.updated_at IS NULL DESC, p.id ASC '
            . 'LIMIT ' . $limit);

        $result = array(
            'processed' => 0,
            'generated' => 0,
            'items' => array(),
            'errors' => array(),
        );

        while ($stmt && ($row = $stmt->fetch(\PDO::FETCH_ASSOC))) {
            $result['processed']++;
            try {
                $path = self::generateProductImage($row);
                if ($path) {
                    $update = $pdo->prepare('UPDATE products SET image_url = :image_url, updated_at = NOW() WHERE id = :id');
                    $update->execute(array(
                        'image_url' => $path,
                        'id' => (int)$row['id'],
                    ));
                    $result['generated']++;
                    $result['items'][] = array(
                        'id' => (int)$row['id'],
                        'path' => $path,
                    );
                }
            } catch (\Throwable $exception) {
                $result['errors'][] = 'Ürün #' . (int)$row['id'] . ' için görsel üretilemedi: ' . $exception->getMessage();
            }
        }

        Settings::set('integration_dalle_last_sync_at', date('Y-m-d H:i:s'));

        return $result;
    }

    /**
     * Store image payload locally and return public path.
     *
     * @param array<string,mixed> $response
     * @param string $filePrefix
     * @return string|null
     */
    private static function storeImageFromResponse(array $response, $filePrefix)
    {
        if (!isset($response['data'][0])) {
            return null;
        }

        $data = $response['data'][0];
        $base64 = isset($data['b64_json']) ? (string)$data['b64_json'] : null;
        $imageUrl = isset($data['url']) ? (string)$data['url'] : null;

        if ($base64) {
            $binary = base64_decode($base64);
            if ($binary === false) {
                throw new RuntimeException('DALL-E yanıtı base64 çözümlenemedi.');
            }

            return self::writeImage($binary, $filePrefix);
        }

        if ($imageUrl) {
            $binary = self::downloadExternalImage($imageUrl);
            return self::writeImage($binary, $filePrefix);
        }

        return null;
    }

    /**
     * @param string $binary
     * @param string $filePrefix
     * @return string
     */
    private static function writeImage($binary, $filePrefix)
    {
        $directory = self::uploadsDirectory();
        $filename = $filePrefix . '-' . bin2hex(random_bytes(6)) . '.png';
        $fullPath = $directory . '/' . $filename;

        if (file_put_contents($fullPath, $binary) === false) {
            throw new RuntimeException('Görsel disk üzerine yazılamadı.');
        }

        return '/assets/uploads/dalle/' . $filename;
    }

    /**
     * @param string $url
     * @return string
     */
    private static function downloadExternalImage($url)
    {
        if (!function_exists('curl_init')) {
            $contents = @file_get_contents($url);
            if ($contents === false) {
                throw new RuntimeException('Görsel indirilemedi.');
            }
            return $contents;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Görsel indirilemedi.');
        }

        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ));

        $binary = curl_exec($ch);
        if ($binary === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Görsel indirilemedi: ' . $error);
        }

        curl_close($ch);

        return (string)$binary;
    }

    /**
     * @param string $relative
     * @return string
     */
    private static function resolvePublicPath($relative)
    {
        $relative = trim((string)$relative);
        if ($relative === '') {
            throw new RuntimeException('Dosya yolu boş.');
        }

        if ($relative[0] !== DIRECTORY_SEPARATOR) {
            $relative = '/' . ltrim($relative, '/');
        }

        return rtrim(self::publicRoot(), '/') . $relative;
    }

    /**
     * @return string
     */
    private static function uploadsDirectory()
    {
        $base = self::publicRoot() . '/assets/uploads/dalle';
        if (!is_dir($base)) {
            if (!mkdir($base, 0775, true) && !is_dir($base)) {
                throw new RuntimeException('assets/uploads/dalle dizini oluşturulamadı.');
            }
        }

        return $base;
    }

    /**
     * @return string
     */
    private static function publicRoot()
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Remove a stored template or generated file.
     *
     * @param string|null $relativePath
     * @return void
     */
    public static function deleteStoredFile($relativePath)
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $fullPath = self::publicRoot() . '/' . ltrim($relativePath, '/');
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * @param string|null $templatePath
     * @return array<string,mixed>
     */
    public static function usageSummary($templatePath = null)
    {
        $model = Settings::get('integration_dalle_model') ?: 'dall-e-3';
        $size = Settings::get('integration_dalle_size') ?: '1024x1024';
        $cost = self::estimateCost($model, $size, $templatePath !== null && $templatePath !== '');

        $summary = array(
            'model' => $model,
            'size' => $size,
            'estimated_cost' => $cost,
        );

        $apiKey = Settings::get('integration_dalle_api_key');
        if ($apiKey) {
            try {
                $billing = OpenAiClient::fetchBillingCredits($apiKey);
                if (isset($billing['total_available'])) {
                    $summary['balance'] = (float)$billing['total_available'];
                }
                if (isset($billing['total_used'])) {
                    $summary['spent'] = (float)$billing['total_used'];
                }
                if (isset($billing['grants']['data'][0]['expires_at'])) {
                    $summary['expires_at'] = (int)$billing['grants']['data'][0]['expires_at'];
                }
            } catch (\Throwable $exception) {
                $summary['billing_error'] = $exception->getMessage();
            }
        }

        $summary['last_sync'] = Settings::get('integration_dalle_last_sync_at');

        return $summary;
    }
}
