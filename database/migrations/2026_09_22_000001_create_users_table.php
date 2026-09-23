<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use VtPhp\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $schema = new Schema();
        $table = $schema->createTable('users');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('email', 'string', ['length' => 255]);
        $table->addColumn('password', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('email_verified_at', 'datetime', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['email']);

        foreach ($schema->toSql($this->db->getDatabasePlatform()) as $sql) {
            $this->db->executeStatement($sql);
        }
    }

    public function down(): void
    {
        $this->db->executeStatement('DROP TABLE IF EXISTS users');
    }
};
