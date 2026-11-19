<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_user', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'project_id']);
            $table->index('project_id');
        });

        DB::table('project_user')->insertUsing(
            ['user_id', 'project_id'],
            DB::table('users')->select('id', 'project_id')->whereNotNull('project_id'),
        );

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }

    public function down(): void
    {
        if (DB::table('project_user')->select('user_id')->groupBy('user_id')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('The previous schema supports only one project per user. Resolve multiple memberships before rollback.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::table('users')->update([
            'project_id' => DB::table('project_user')->select('project_id')->whereColumn('user_id', 'users.id'),
        ]);

        Schema::dropIfExists('project_user');
    }
};
