<?php

namespace App\Observers;

use App\Model\Sound;

class SoundsObserver
{
    public function deleting(Sound $sound): void
    {
        foreach ($sound->attachments()->get() as $attachment) {
            $attachment->delete();
        }
    }
}
