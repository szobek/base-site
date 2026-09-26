<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pages');
    }

    public function down(): void
    {
        // The legal pages live in Angular components, not in the database.
    }
};
