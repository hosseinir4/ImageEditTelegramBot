<?php

declare(strict_types=1);

namespace ImageBot;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $name = $_ENV['DB_NAME'] ?? '';
        $user = $_ENV['DB_USER'] ?? '';
        $password = $_ENV['DB_PASS'] ?? '';

        if ($name === '' || $user === '') {
            throw new RuntimeException('Set DB_HOST, DB_NAME, DB_USER, and DB_PASS in .env');
        }

        self::$connection = new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        );

        return self::$connection;
    }
}
