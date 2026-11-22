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
            $table->char('object_hash', 64);
            $table->foreignId('scope_id')->constrained('scopes')->cascadeOnDelete();
            $table->primary(['object_hash', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scope_grants');
        Schema::dropIfExists('scopes');
    }
};
