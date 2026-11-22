<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scopes', function (Blueprint $table) {
            $table->id();
            $table->string('program_identifier');
            $table->string('name');
            $table->unique(['program_identifier', 'name']);
        });

        Schema::create('scope_grants', function (Blueprint $table) {
            $identifier = $table->string('identifier', 700);

            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $identifier->charset('utf8mb4')->collation('utf8mb4_bin');
            }

            $table->foreignId('scope_id')->constrained('scopes')->cascadeOnDelete();
            $table->primary(['identifier', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scope_grants');
        Schema::dropIfExists('scopes');
    }
};
