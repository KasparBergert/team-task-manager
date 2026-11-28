<?php

use App\Helpers\SystemVersioning;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SystemVersioning::set('users', enabled: true);
        SystemVersioning::set('projects', enabled: true);
        SystemVersioning::set('tasks', enabled: true);
    }

    /**
     * Dropping system versioning also removes the historical rows.
     */
    public function down(): void
    {
        SystemVersioning::set('tasks', enabled: false);
        SystemVersioning::set('projects', enabled: false);
        SystemVersioning::set('users', enabled: false);
    }
};
