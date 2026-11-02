<?php

namespace Database\Seeders;

use App\Services\ConversionTracking\ConversionTrackingPermissions;
use Illuminate\Database\Seeder;

class ConversionTrackingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        ConversionTrackingPermissions::grant();
    }
}
