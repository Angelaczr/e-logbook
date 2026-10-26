<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id_number', 64)->nullable()->unique();
            $table->string('job_title')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 32)->default(UserRole::Employee->value);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();

            $table->index('supervisor_id');
            $table->index(['department_id', 'role']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $values = $this->quotedValues(UserRole::cases());
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ({$values}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['supervisor_id']);
            $table->dropIndex(['department_id', 'role']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['supervisor_id']);
            $table->dropUnique(['employee_id_number']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'employee_id_number',
                'job_title',
                'department_id',
                'supervisor_id',
                'role',
                'is_active',
            ]);
        });
    }

    /**
     * @param  list<UserRole>  $cases
     */
    private function quotedValues(array $cases): string
    {
        return implode(', ', array_map(fn (UserRole $case) => "'{$case->value}'", $cases));
    }
};
