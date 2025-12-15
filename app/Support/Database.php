<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $conn = null;

    public static function connection(): PDO
    {
        if (self::$conn === null) {
            $dbPath = self::$overridePath ?? storage_path('database/app.sqlite');
            $dsn = 'sqlite:' . $dbPath;
            try {
                $pdo = new PDO($dsn);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->exec('PRAGMA foreign_keys = ON;');
                self::$conn = $pdo;
            } catch (PDOException $e) {
                throw new PDOException('Failed to connect to SQLite: ' . $e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$conn;
    }

    private static ?string $overridePath = null;
    public static function setDatabasePath(string $path): void {
        self::$overridePath = $path;
        self::$conn = null; // Reset connection
    }
}
