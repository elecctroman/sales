<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    /**
     * @var PDO|null
     */
    private static $connection = null;

    /**
     * @var array<string,bool>
     */
    private static $columnCache = array();

    /**
     * Initialise the database connection and cache the PDO instance.
     *
     * @param array $config
     * @return void
     */
    public static function initialize(array $config)
    {
        if (self::$connection instanceof PDO) {
            return;
        }

        $host = isset($config['host']) ? $config['host'] : 'localhost';
        $name = isset($config['name']) ? $config['name'] : '';
        $user = isset($config['user']) ? $config['user'] : '';
        $password = isset($config['password']) ? $config['password'] : '';
        $port = isset($config['port']) && (string)$config['port'] !== '' ? (int)$config['port'] : null;
        $socket = isset($config['socket']) ? $config['socket'] : null;

        if ($socket) {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $socket, $name);
        } elseif ($port !== null) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        } else {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
        }

        try {
            self::$connection = new PDO($dsn, $user, $password, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
        } catch (PDOException $exception) {
            throw new PDOException(
                'Veritabanýna baðlanýrken bir hata oluþtu: ' . $exception->getMessage(),
                (int)$exception->getCode(),
                $exception
            );
        }
    }

    /**
     * @return PDO
     */
    public static function connection()
    {
        if (!(self::$connection instanceof PDO)) {
            throw new PDOException('Veritabaný baðlantýsý baþlatýlmadý.');
        }

        return self::$connection;
    }

    /**
     * Check if a given table contains the specified column.
     *
     * @param string $table
     * @param string $column
     * @return bool
     */
    public static function tableHasColumn($table, $column)
    {
        $table = trim((string)$table);
        $column = trim((string)$column);
        if ($table === '' || $column === '') {
            return false;
        }

        $cacheKey = strtolower($table) . '.' . strtolower($column);
        if (array_key_exists($cacheKey, self::$columnCache)) {
            return self::$columnCache[$cacheKey];
        }

        try {
            $pdo = self::connection();
        } catch (\Throwable $exception) {
            self::$columnCache[$cacheKey] = false;
            return false;
        }

        $escapedTable = str_replace('`', '``', $table);

        try {
            $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $escapedTable . '` LIKE :column');
            $stmt->execute(array('column' => $column));
            $exists = (bool)$stmt->fetchColumn();
        } catch (\Throwable $exception) {
            $exists = false;
        }

        self::$columnCache[$cacheKey] = $exists;
        return $exists;
    }
}
