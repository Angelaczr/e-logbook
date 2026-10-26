<?php

use App\Enums\LogbookStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logbook_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logbook_id')->constrained('logbooks');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['logbook_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $values = implode(', ', array_map(fn (LogbookStatus $s) => "'{$s->value}'", LogbookStatus::cases()));
            DB::statement("ALTER TABLE logbook_status_histories ADD CONSTRAINT logbook_status_histories_from_status_check CHECK (from_status IS NULL OR from_status IN ({$values}))");
            DB::statement("ALTER TABLE logbook_status_histories ADD CONSTRAINT logbook_status_histories_to_status_check CHECK (to_status IN ({$values}))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_status_histories');
    }
};
