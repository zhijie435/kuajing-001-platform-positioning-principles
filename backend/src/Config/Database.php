<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $dbPath = __DIR__ . '/../../data/crm.db';
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        self::$connection = new PDO("sqlite:{$dbPath}", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        self::createTables(self::$connection);
        self::seedData(self::$connection);

        return self::$connection;
    }

    private static function createTables(PDO $pdo): void
    {
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');

        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            display_name TEXT NOT NULL DEFAULT '',
            role TEXT NOT NULL DEFAULT 'user',
            status TEXT NOT NULL DEFAULT 'active',
            last_login_at TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS licenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            license_key TEXT NOT NULL UNIQUE,
            status TEXT NOT NULL DEFAULT 'inactive',
            activated_at TEXT,
            expires_at TEXT NOT NULL,
            max_users INTEGER NOT NULL DEFAULT 1,
            domain TEXT NOT NULL DEFAULT '*',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS boundary_rules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rule_type TEXT NOT NULL,
            rule_name TEXT NOT NULL,
            rule_value TEXT NOT NULL,
            is_enabled INTEGER NOT NULL DEFAULT 1,
            description TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS violation_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rule_id INTEGER,
            rule_type TEXT NOT NULL,
            violation_detail TEXT NOT NULL,
            client_ip TEXT NOT NULL DEFAULT '',
            request_path TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            company TEXT NOT NULL DEFAULT '',
            phone TEXT NOT NULL DEFAULT '',
            email TEXT NOT NULL DEFAULT '',
            source TEXT NOT NULL DEFAULT '',
            industry TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'potential',
            level TEXT NOT NULL DEFAULT 'C',
            assigned_to INTEGER NOT NULL DEFAULT 0,
            remark TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS follow_ups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            type TEXT NOT NULL DEFAULT 'visit',
            content TEXT NOT NULL DEFAULT '',
            result TEXT NOT NULL DEFAULT '',
            next_action TEXT NOT NULL DEFAULT '',
            next_date TEXT,
            follow_up_by INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
    }

    private static function seedData(PDO $pdo): void
    {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        $pdo->exec("INSERT INTO users (username, password, display_name, role, status) VALUES (
            'admin',
            '" . password_hash('password', PASSWORD_BCRYPT) . "',
            '系统管理员',
            'admin',
            'active'
        )");

        $pdo->exec("INSERT INTO licenses (license_key, status, activated_at, expires_at, max_users, domain) VALUES (
            'CRM-TRIAL-2024-XXXX',
            'active',
            datetime('now'),
            datetime('now', '+30 days'),
            5,
            '*'
        )");

        $pdo->exec("INSERT INTO boundary_rules (rule_type, rule_name, rule_value, description) VALUES
            ('domain_whitelist', '域名白名单', '*.{yourcompany}.com,localhost', '允许访问的域名列表'),
            ('ip_blacklist', 'IP黑名单', '10.0.0.100,192.168.1.200', '禁止访问的IP地址'),
            ('concurrent_max', '最大并发数', '50', '系统允许的最大并发连接数'),
            ('data_export', '数据导出限制', 'enabled', '是否允许数据导出功能')
        ");
    }
}
