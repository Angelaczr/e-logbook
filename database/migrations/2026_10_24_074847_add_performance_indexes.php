<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PostgreSQL tidak membuat index otomatis untuk foreign key; indeks di sini mengikuti
     * query pengingat (status + submitted_at), analitik (join proyek/departemen), dan audit.
     */
    public function up(): void
    {
        Schema::table('logbooks', function (Blueprint $table) {
            $table->index(['status', 'submitted_at']);
            $table->index('approved_by');
        });

        Schema::table('logbook_items', function (Blueprint $table) {
            $table->index('project_id');
        });

        Schema::table('logbook_status_histories', function (Blueprint $table) {
            $table->index('actor_id');
            $table->index(['to_status', 'created_at']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
        });

        Schema::table('logbook_status_histories', function (Blueprint $table) {
            $table->dropIndex(['to_status', 'created_at']);
            $table->dropIndex(['actor_id']);
        });

        Schema::table('logbook_items', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });

        Schema::table('logbooks', function (Blueprint $table) {
            $table->dropIndex(['approved_by']);
            $table->dropIndex(['status', 'submitted_at']);
        });
    }
};
