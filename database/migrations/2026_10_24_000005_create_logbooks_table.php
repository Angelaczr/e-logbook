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
        Schema::create('logbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->date('work_date');
            $table->string('shift', 32)->default('regular');
            $table->string('status', 32)->default(LogbookStatus::Draft->value);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('total_minutes')->default(0);
            $table->unsignedInteger('revision_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'work_date', 'shift']);
            $table->index(['status', 'work_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $values = implode(', ', array_map(fn (LogbookStatus $s) => "'{$s->value}'", LogbookStatus::cases()));
            DB::statement("ALTER TABLE logbooks ADD CONSTRAINT logbooks_status_check CHECK (status IN ({$values}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE logbooks DROP CONSTRAINT IF EXISTS logbooks_status_check');
        }

        Schema::dropIfExists('logbooks');
    }
};
