<?php
require_once __DIR__ . '/../config/database.php';

class Database {
    private static ?mysqli $instance = null;

    public static function getInstance(): mysqli {
        if (self::$instance === null) {
            $cfg = require __DIR__ . '/../config/database.php';
            self::$instance = new mysqli($cfg['host'], $cfg['username'], $cfg['password'], $cfg['database'], (int)$cfg['port']);
            if (self::$instance->connect_error) {
                throw new RuntimeException('Database connection failed: ' . self::$instance->connect_error);
            }
            self::$instance->set_charset($cfg['charset']);
        }
        return self::$instance;
    }

    public static function init(): void {
        self::getInstance();
    }
}

Database::init();
