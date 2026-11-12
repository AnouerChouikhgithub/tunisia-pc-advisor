<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = require APP_ROOT . '/config/database.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            self::$pdo->exec("SET time_zone = '+01:00'");
        } catch (PDOException $exception) {
            error_log('Database connection failed: ' . $exception->getMessage());
            throw new RuntimeException(
                'The database is unavailable. Import database/schema.sql and database/seed.sql, then check the DB settings in your .env file.'
            );
        }

        return self::$pdo;
    }

    public static function isAvailable(): bool
    {
        try {
            self::connection();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function tableExists(string $table): bool
    {
        try {
            $stmt = self::connection()->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
            );
            $stmt->execute([$table]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function __construct()
    {
    }
}
