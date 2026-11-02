<?php

namespace App\Filament\Resources\Reels\Pages;

use App\Filament\Resources\Reels\ReelResource;
use App\Model\Reel;
use App\Services\Moderation\PublicationReviewService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** @extends CreateRecord<Reel> */
class CreateReel extends CreateRecord
{
    protected static string $resource = ReelResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            unset($data['status']);
            $reel = parent::handleRecordCreation($data);
            app(PublicationReviewService::class)->submit($reel);

            return $reel->refresh();
        });
    }
}
