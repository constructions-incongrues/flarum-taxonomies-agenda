<?php

namespace Mi\AgendaTimeline\Tests\integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Base case for integration tests that exercise real SQL against a MariaDB
 * pre-migrated with flarum core + flarum/tags + flamarkt/taxonomies +
 * constructions-incongrues/taxonomies-agenda.
 *
 * Each test runs inside a transaction that is rolled back on teardown,
 * so fixtures do not leak across tests.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static ?Capsule $capsule = null;
    protected ConnectionInterface $db;

    public static function setUpBeforeClass(): void
    {
        if (self::$capsule !== null) {
            return;
        }

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => getenv('DB_HOST') ?: 'db',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE') ?: 'flarum_test',
            'username' => getenv('DB_USERNAME') ?: 'flarum',
            'password' => getenv('DB_PASSWORD') ?: 'flarum',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        self::$capsule = $capsule;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = self::$capsule->getConnection();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        parent::tearDown();
    }
}
