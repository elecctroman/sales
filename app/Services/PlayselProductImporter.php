<?php

namespace App\Services;

use App\Database;
use App\Helpers;
use PDO;

class PlayselProductImporter
{
    /**
     * @param PDO                             $pdo
     * @param array<int, array<string,mixed>> $products
     * @return array{imported:int,updated:int,skipped:int}
     */
    public static function sync(PDO $pdo, array $products)
    {
        $stats = array(
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
        );

        if (!$products) {
            return $stats;
        }

        $categoryId = self::ensureCategory($pdo, 'Playsel');

        $supportsShortDescription = Database::tableHasColumn('products', 'short_description');
        $supportsImageUrl = Database::tableHasColumn('products', 'image_url');

        $findStmt = $pdo->prepare('SELECT id FROM products WHERE sku = :sku LIMIT 1');
        $updateColumns = array(
            'name = :name',
            'category_id = :category_id',
            'cost_price_try = :cost_price_try',
            'price = :price',
            'description = :description',
            'sku = :sku',
            'status = :status',
            'updated_at = NOW()',
        );

        if ($supportsShortDescription) {
            $updateColumns[] = 'short_description = :short_description';
        }

        if ($supportsImageUrl) {
            $updateColumns[] = 'image_url = :image_url';
        }

        $updateSql = 'UPDATE products SET ' . implode(', ', $updateColumns) . ' WHERE id = :id';
        $updateStmt = $pdo->prepare($updateSql);

        $insertColumns = array(
            'category_id',
            'name',
            'sku',
            'cost_price_try',
            'price',
            'description',
            'status',
            'created_at',
        );
        $insertValues = array(
            ':category_id',
            ':name',
            ':sku',
            ':cost_price_try',
            ':price',
            ':description',
            ':status',
            'NOW()',
        );

        if ($supportsShortDescription) {
            $insertColumns[] = 'short_description';
            $insertValues[] = ':short_description';
        }

        if ($supportsImageUrl) {
            $insertColumns[] = 'image_url';
            $insertValues[] = ':image_url';
        }

        $insertSql = sprintf(
            'INSERT INTO products (%s) VALUES (%s)',
            implode(', ', $insertColumns),
            implode(', ', $insertValues)
        );
        $insertStmt = $pdo->prepare($insertSql);

        foreach ($products as $product) {
            if (!is_array($product)) {
                $stats['skipped']++;
                continue;
            }

            $remoteId = isset($product['productID']) ? (int)$product['productID'] : 0;
            $name = isset($product['productName']) ? trim((string)$product['productName']) : '';

            if ($remoteId <= 0 || $name === '') {
                $stats['skipped']++;
                continue;
            }

            $sku = 'playsel-' . $remoteId;
            $status = isset($product['status']) ? (int)$product['status'] : 0;
            $totalStock = isset($product['totalStock']) ? (int)$product['totalStock'] : 0;
            $isActive = $status === 1 && $totalStock !== 0;

            $data = isset($product['productData']) && is_array($product['productData']) ? $product['productData'] : array();
            $description = isset($data['productDescription']) ? trim((string)$data['productDescription']) : '';
            $shortDescription = $description !== '' ? Helpers::truncate($description, 160, '...') : '';
            $imageUrl = isset($data['productMainImage']) ? trim((string)$data['productMainImage']) : '';

            $salePrice = isset($product['salePrice']) ? (float)$product['salePrice'] : 0.0;
            if ($salePrice < 0) {
                $salePrice = 0.0;
            }

            $costPriceTry = $salePrice;
            $price = Helpers::priceFromCostTry($costPriceTry);
            $statusLabel = $isActive ? 'active' : 'inactive';

            $findStmt->execute(array('sku' => $sku));
            $existingId = $findStmt->fetchColumn();

            $common = array(
                'name' => $name,
                'category_id' => $categoryId,
                'cost_price_try' => $costPriceTry,
                'price' => $price,
                'description' => $description !== '' ? $description : null,
                'sku' => $sku,
                'status' => $statusLabel,
            );

            if ($supportsShortDescription) {
                $common['short_description'] = $shortDescription !== '' ? $shortDescription : null;
            }

            if ($supportsImageUrl) {
                $common['image_url'] = $imageUrl !== '' ? $imageUrl : null;
            }

            if ($existingId) {
                $params = $common;
                $params['id'] = (int)$existingId;
                $updateStmt->execute($params);
                $stats['updated']++;
            } else {
                $insertStmt->execute($common);
                $stats['imported']++;
            }
        }

        return $stats;
    }

    /**
     * @param PDO    $pdo
     * @param string $name
     * @return int
     */
    private static function ensureCategory(PDO $pdo, $name)
    {
        $name = trim((string)$name);
        if ($name === '') {
            $name = 'Dis Saglayici';
        }

        $existing = $pdo->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
        $existing->execute(array('name' => $name));
        $categoryId = $existing->fetchColumn();
        if ($categoryId) {
            return (int)$categoryId;
        }

        $insert = $pdo->prepare('INSERT INTO categories (parent_id, name, description) VALUES (NULL, :name, :description)');
        $insert->execute(array(
            'name' => $name,
            'description' => 'Playsel saglayicisindan otomatik olarak senkronize edilen urunler.',
        ));

        return (int)$pdo->lastInsertId();
    }
}
