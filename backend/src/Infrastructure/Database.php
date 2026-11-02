<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;

/** Database connection settings read from the environment, and the PDO factory. */
final readonly class Database
{
    public function __construct(
        public string $host,
        public int $port,
        public string $name,
        public string $user,
        public string $password,
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            host: self::env('DB_HOST', '127.0.0.1'),
            port: (int) self::env('DB_PORT', '3306'),
            name: self::env('DB_NAME', 'notes'),
            user: self::env('DB_USER', 'notes'),
            password: self::env('DB_PASSWORD', ''),
        );
    }

    public function connect(): PDO
    {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $this->host, $this->port, $this->name),
            $this->user,
            $this->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real server-side prepared statements, and integers come back as integers.
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
        // All timestamps are stored and returned in UTC; the client formats them locally.
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? $default : $value;
    }
}
