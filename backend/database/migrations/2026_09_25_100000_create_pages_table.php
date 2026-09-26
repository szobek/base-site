<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('body');
            $table->timestamps();
        });

        $now = now();

        DB::table('pages')->insert([
            [
                'slug' => 'impresszum',
                'title' => 'Impresszum',
                'body' => '<p>Ezt a szöveget az admin felületen cserélheted a vállalkozás adataira: név, székhely, elérhetőség, adószám és a tárhelyszolgáltató.</p>',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'adatvedelem',
                'title' => 'Adatvédelem',
                'body' => '<p>Ezt a szöveget az admin felületen cserélheted az adatkezelési tájékoztatóra. Írd le, milyen adatot kezeled, meddig és ki fér hozzá.</p>',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
