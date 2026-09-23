<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use VtPhp\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $schema = new Schema();
        $table = $schema->createTable('password_reset_tokens');
        $table->addColumn('email', 'string', ['length' => 255]);
        $table->addColumn('token', 'string', ['length' => 255]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->setPrimaryKey(['email']);

        foreach ($schema->toSql($this->db->getDatabasePlatform()) as $sql) {
            $this->db->executeStatement($sql);
        }
    }

    public function down(): void
    {
        $this->db->executeStatement('DROP TABLE IF EXISTS password_reset_tokens');
    }
};
