<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite doesn't enforce enum — nothing to do
            return;
        }

        DB::statement("ALTER TABLE autofix_proposals MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'applied', 'failed', 'error', 'notify_only', 'dry_run') DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE autofix_proposals MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'applied', 'failed') DEFAULT 'pending'");
    }
};
