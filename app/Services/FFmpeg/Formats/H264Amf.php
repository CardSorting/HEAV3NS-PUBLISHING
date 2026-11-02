<?php

namespace App\Services\FFmpeg\Formats;

use FFMpeg\Format\Video\DefaultVideo;

class H264Amf extends DefaultVideo
{
    public function __construct(string $audioCodec = 'aac')
    {
        $this->audioCodec = $audioCodec;
        $this->videoCodec = 'h264_amf';
    }

    public function getAvailableVideoCodecs(): array
    {
        return ['h264_amf'];
    }

    public function getAvailableAudioCodecs(): array
    {
        return ['aac', 'libfdk_aac', 'libmp3lame'];
    }

    public function supportBFrames(): bool
    {
        return true;
    }
}
