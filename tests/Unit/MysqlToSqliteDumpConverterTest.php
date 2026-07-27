<?php

namespace Tests\Unit;

use App\Support\MysqlToSqliteDumpConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MysqlToSqliteDumpConverterTest extends TestCase
{
    #[Test]
    public function it_detects_mysql_dumps(): void
    {
        $this->assertTrue(MysqlToSqliteDumpConverter::looksLikeMysqlDump(
            "CREATE TABLE `users` (`id` int(11) NOT NULL AUTO_INCREMENT) ENGINE=InnoDB;",
        ));

        $this->assertFalse(MysqlToSqliteDumpConverter::looksLikeMysqlDump(
            'CREATE TABLE "users" ("id" INTEGER PRIMARY KEY AUTOINCREMENT);',
        ));
    }

    #[Test]
    public function converted_dump_imports_into_sqlite(): void
    {
        $mysql = <<<'SQL'
        SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
        SET time_zone = "+00:00";
        /*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;

        DROP TABLE IF EXISTS `employees`;
        CREATE TABLE `employees` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
          `status` enum('active','inactive') NOT NULL DEFAULT 'active',
          `salary` decimal(10,2) unsigned DEFAULT NULL,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `employees_name_unique` (`name`),
          KEY `employees_status_index` (`status`)
        ) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;

        LOCK TABLES `employees` WRITE;
        INSERT INTO `employees` (`id`, `name`, `status`, `salary`, `created_at`) VALUES
        (1, 'O\'Brien', 'active', 1500.50, '2026-01-01 08:00:00'),
        (2, 'Line\nBreak', 'inactive', NULL, '2026-01-02 09:00:00');
        UNLOCK TABLES;
        SQL;

        $sqlite = (new MysqlToSqliteDumpConverter)->convert($mysql);

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        foreach (array_filter(array_map('trim', explode(";\n", $sqlite))) as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }

        $rows = $pdo->query('SELECT id, name, status, salary FROM "employees" ORDER BY id')
            ->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(2, $rows);
        $this->assertSame("O'Brien", $rows[0]['name']);
        $this->assertSame('active', $rows[0]['status']);
        $this->assertSame("Line\nBreak", $rows[1]['name']);
        $this->assertNull($rows[1]['salary']);

        // AUTOINCREMENT primary key works after conversion.
        $pdo->exec('INSERT INTO "employees" ("name", "status") VALUES (\'New\', \'active\')');
        $this->assertSame('3', $pdo->lastInsertId());
    }

    #[Test]
    public function it_handles_charset_collate_indexes_and_prefix_lengths(): void
    {
        // Reproduces the "unrecognized token 8mb4_unicode_ci" failure and other edge cases.
        $mysql = <<<'SQL'
        CREATE TABLE `docs` (
          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
          `body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
          `email` varchar(191) COLLATE=utf8mb4_unicode_ci DEFAULT NULL,
          `flag` tinyint(1) NOT NULL DEFAULT b'0',
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `docs_email_unique` (`email`(191)),
          KEY `docs_title_index` (`title`) USING BTREE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        INSERT INTO `docs` (`id`, `title`, `body`, `email`, `flag`) VALUES
        (1, 'multi\nline\ttext', 'has ; semicolon and \' quote', 'a@b.com', 1);
        SQL;

        $sqlite = (new MysqlToSqliteDumpConverter)->convert($mysql);

        $this->assertStringNotContainsString('8mb4', $sqlite);
        $this->assertStringNotContainsString('COLLATE', $sqlite);
        $this->assertStringNotContainsString('CHARACTER SET', $sqlite);
        $this->assertStringNotContainsString('USING BTREE', $sqlite);

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec($sqlite); // sqlite3_exec runs every statement in the string

        $row = $pdo->query('SELECT * FROM "docs"')->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $row['id']);
        $this->assertSame("multi\nline\ttext", $row['title']);
        $this->assertStringContainsString('; semicolon', $row['body']);
        $this->assertStringContainsString("' quote", $row['body']);
    }
}
