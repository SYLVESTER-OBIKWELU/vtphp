<?php

declare(strict_types=1);

namespace VtPhp\Database\Migrations;

use Doctrine\DBAL\Connection;

abstract class Migration
{
    protected Connection $db;

    public function setConnection(Connection $db): static
    {
        $this->db = $db;

        return $this;
    }

    abstract public function up(): void;

    abstract public function down(): void;
}
