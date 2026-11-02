<?php

namespace App\Services\FFmpeg\Formats;

use FFMpeg\Format\Video\DefaultVideo;

class H264Nvenc extends DefaultVideo
{
    public function __construct(string $audioCodec = 'aac')
    {
        $this->audioCodec = $audioCodec;
        $this->videoCodec = 'h264_nvenc';
    }

    public function getAvailableVideoCodecs(): array
    {
        return ['h264_nvenc'];
    }

    public function getAvailableAudioCodecs(): array
    {
        return ['aac', 'libfdk_aac', 'libmp3lame'];
    }

    /**
     * NVENC supports B-frames.
     */
    public function supportBFrames(): bool
    {
        return true;
    }
}
