<?php

use App\Enums\ProgressStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logbook_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logbook_id')->constrained('logbooks')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->text('task_description');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('duration_minutes');
            $table->string('status_progress', 32)->default(ProgressStatus::InProgress->value);
            $table->string('output_link_or_attachment', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['logbook_id', 'sort_order']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $values = implode(', ', array_map(fn (ProgressStatus $s) => "'{$s->value}'", ProgressStatus::cases()));
            DB::statement("ALTER TABLE logbook_items ADD CONSTRAINT logbook_items_status_progress_check CHECK (status_progress IN ({$values}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE logbook_items DROP CONSTRAINT IF EXISTS logbook_items_status_progress_check');
        }

        Schema::dropIfExists('logbook_items');
    }
};
