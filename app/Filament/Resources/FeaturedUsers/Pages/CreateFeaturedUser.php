<?php

namespace App\Filament\Resources\FeaturedUsers\Pages;

use App\Filament\Resources\FeaturedUsers\FeaturedUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeaturedUser extends CreateRecord
{
    protected static string $resource = FeaturedUserResource::class;
}
