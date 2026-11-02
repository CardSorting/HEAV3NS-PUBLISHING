<?php

namespace App\Observers;

use App\Model\Stream;
use App\Providers\StreamsServiceProvider;
use Illuminate\Support\Facades\Log;

class StreamsObserver
{
    /**
     * Listen to the Attachment deleted event.
     *
     * @param  Stream  $stream
     * @return void
     */
    public function deleted(Stream $stream)
    {
        if($stream->driver === Stream::PUSHR_DRIVER){
            try {
                StreamsServiceProvider::destroyPushrStream($stream->pushr_id);
            } catch (\Exception $exception) {
                Log::error("Failed deleting stopping stream for: ".$stream->pushr_id.", e: ".$exception->getMessage());
            }
        }
    }

    public function saving(Stream $stream)
    {
        if ($stream->getOriginal('status') == 'in-progress' && ($stream->status == 'ended' || $stream->status == 'deleted')) {
            try {
                StreamsServiceProvider::destroyPushrStream($stream->pushr_id);
            } catch (\Exception $exception) {
                Log::error("Failed deleting stopping stream for: ".$stream->pushr_id.", e: ".$exception->getMessage());
            }
        }
    }
}
