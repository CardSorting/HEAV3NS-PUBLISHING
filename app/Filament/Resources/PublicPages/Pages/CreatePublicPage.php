<?php

namespace App\Filament\Resources\PublicPages\Pages;

use App\Filament\Resources\PublicPages\PublicPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePublicPage extends CreateRecord
{
    protected static string $resource = PublicPageResource::class;
}
