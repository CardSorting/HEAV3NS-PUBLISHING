<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

class V910 extends Migration
{
    /**
     * Post-admin panel migrations.
     */
    public function up(): void
    {

        // Deleting pre 7.6.0 (laravel 9 used lang file)
        $langPath = resource_path('lang');
        if (File::exists($langPath)) {
            File::deleteDirectory($langPath);
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {



    }
}
