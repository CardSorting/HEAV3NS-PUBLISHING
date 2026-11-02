<?php

namespace App\Services\Media;

use App\Model\User;
use FFMpeg\Filters\Video\CustomFilter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use ProtoneMedia\LaravelFFMpeg\Filters\WatermarkFactory;

class WatermarkService
{
    /**
     * Resolve watermark image path with fallback.
     * (Same behavior as your old getWatermarkPath()).
     */
    public function watermarkPath(): string
    {
        $watermark = getSetting('media.watermark_image');

        return $watermark ?: public_path('img/logo-black.png');
    }

    /**
     * Returns: none|image|url
     * - url mode is FFmpeg-only (you already handle coconut not supporting it).
     */
    public function mode(): string
    {
        if (!getSetting('media.apply_watermark')) {
            return 'none';
        }

        if (getSetting('media.use_url_watermark')) {
            return 'url';
        }

        if (getSetting('media.watermark_image')) {
            return 'image';
        }

        return 'none';
    }

    /**
     * Normalized watermark options.
     */
    public function opts(): array
    {
        $pos = (string) (getSetting('media.watermark_position') ?: 'bottom-right');

        return [
            'position' => in_array($pos, ['top-left', 'top-right', 'bottom-left', 'bottom-right'], true) ? $pos : 'bottom-right',
            'scale'    => max(5, min(60, (int) (getSetting('media.watermark_scale_percent') ?: 25))),
            'opacity'  => max(0, min(100, (int) (getSetting('media.watermark_opacity') ?: 80))),
            'mx'       => 25, // margins - keep same behavior
            'my'       => 25,
        ];
    }

    /**
     * Returns [xAnchor, mx, yAnchor, my]
     * Anchors are "left|right" + "top|bottom".
     */
    public function mapPositionToOffsets(string $pos, int $mx, int $my): array
    {
        return match ($pos) {
            'top-left'     => ['left',  $mx, 'top',    $my],
            'top-right'    => ['right', $mx, 'top',    $my],
            'bottom-left'  => ['left',  $mx, 'bottom', $my],
            default        => ['right', $mx, 'bottom', $my], // bottom-right
        };
    }

    /**
     * Apply watermark to FFmpeg video pipeline.
     *
     * Returns:
     *  - tmpWatermarkFile => string|null (created only for image watermark mode)
     */
    public function applyToFfmpegVideo($video, string $fileId, ?int $ownerId = null): array
    {
        $mode = $this->mode();
        if ($mode === 'none') {
            return ['tmpWatermarkFile' => null];
        }

        $w = $this->opts();
        $dimensions = $video->getVideoStream()->getDimensions();

        $tmpWatermarkFile = null;

        // -----------------------------------
        // IMAGE WATERMARK (logo)
        // -----------------------------------
        if ($mode === 'image') {
            $watermark = Image::make($this->watermarkPath());

            // Resize watermark based on % of video width
            $watermarkWidth = (int) round($dimensions->getWidth() * ($w['scale'] / 100));
            $watermark->resize($watermarkWidth, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // We rely on PNG transparency provided by the user
            $tmpWatermarkFile = 'watermark-'.$fileId.'.png';
            $watermark->encode('png', 100);
            Storage::disk('tmp')->put($tmpWatermarkFile, (string) $watermark);

            [$xAnchor, $mx, $yAnchor, $my] =
                $this->mapPositionToOffsets($w['position'], $w['mx'], $w['my']);

            $video->addWatermark(function (WatermarkFactory $wm) use ($tmpWatermarkFile, $xAnchor, $mx, $yAnchor, $my) {
                $wm = $wm->fromDisk('tmp')->open($tmpWatermarkFile);

                $xAnchor === 'left' ? $wm->left($mx) : $wm->right($mx);
                $yAnchor === 'top' ? $wm->top($my) : $wm->bottom($my);
            });
        }

        // -----------------------------------
        // TEXT WATERMARK (profile URL) - FFmpeg only
        // -----------------------------------
        if ($mode === 'url') {
            $text = $this->profileUrlWatermark($ownerId);

            $fontSize = (int) round(3 / 100 * $dimensions->getWidth());
            $alpha = max(0, min(100, (int) $w['opacity'])) / 100;

            [$xAnchor, $mx, $yAnchor, $my] =
                $this->mapPositionToOffsets($w['position'], $w['mx'], $w['my']);

            $x = $xAnchor === 'left' ? $mx : "(w-text_w)-{$mx}";
            $y = $yAnchor === 'top' ? $my : "(h-text_h)-{$my}";

            $font = config('laravel-ffmpeg.ffmpeg.font', 'Verdana');

            $video->addFilter(new CustomFilter(
                "drawtext=text='".addslashes($text)."'".
                ":fontfile='{$font}'".
                ":fontsize={$fontSize}".
                ":fontcolor=white@{$alpha}".
                ":x={$x}:y={$y}"
            ));
        }

        return ['tmpWatermarkFile' => $tmpWatermarkFile];
    }

    /**
     * Apply watermark to an Intervention Image instance (images uploads).
     */
    public function applyToInterventionImage($img): void
    {
        $mode = $this->mode();
        if ($mode === 'none') {
            return;
        }

        $w = $this->opts();

        // -----------------------------------
        // IMAGE WATERMARK (logo)
        // -----------------------------------
        if ($mode === 'image') {
            $watermark = Image::make($this->watermarkPath());

            // watermark width = N% of image width
            $watermarkWidth = (int) round($img->width() * ($w['scale'] / 100));
            $watermark->resize($watermarkWidth, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            $posMap = [
                'top-left'     => 'top-left',
                'top-right'    => 'top-right',
                'bottom-left'  => 'bottom-left',
                'bottom-right' => 'bottom-right',
            ];

            $img->insert(
                $watermark,
                $posMap[$w['position']] ?? 'bottom-right',
                $w['mx'],
                $w['my']
            );
        }

        // -----------------------------------
        // TEXT WATERMARK (profile URL)
        // -----------------------------------
        if ($mode === 'url') {
            $text = $this->profileUrlWatermark();

            $fontSize = (int) round(3 / 100 * $img->width());
            $alpha = max(0, min(100, (int) $w['opacity'])) / 100;

            $x = match ($w['position']) {
                'top-left', 'bottom-left' => $w['mx'],
                default => $img->width() - $w['mx'],
            };

            $y = match ($w['position']) {
                'top-left', 'top-right' => $w['my'],
                default => $img->height() - $w['my'],
            };

            $align = in_array($w['position'], ['top-left', 'bottom-left'], true) ? 'left' : 'right';
            $valign = in_array($w['position'], ['top-left', 'top-right'], true) ? 'top' : 'bottom';

            $img->text($text, $x, $y, function ($font) use ($fontSize, $alpha, $align, $valign) {
                $font->file(public_path('/fonts/OpenSans-Semibold.ttf'));
                $font->size($fontSize);
                $font->color([255, 255, 255, $alpha]);
                $font->align($align);
                $font->valign($valign);
                $font->angle(0);
            });
        }
    }

    protected function profileUrlWatermark(?int $ownerId = null): string
    {
        // Background jobs have no authenticated request. An explicit owner must
        // always take precedence, including when processing is run inline.
        $username = $ownerId !== null
            ? User::query()->whereKey($ownerId)->value('username')
            : Auth::user()?->username;

        if (!is_string($username) || trim($username) === '') {
            throw new \RuntimeException('The media owner could not be resolved for the profile URL watermark.');
        }

        return str_replace(['https://', 'http://', 'www.'], '', route('profile', ['username' => $username]));
    }

    /**
     * Apply watermark to Coconut job data.
     * Only image watermark is supported (URL watermark isn't).
     */
    public function applyToCoconutJobData(array &$jobData): void
    {
        $mode = $this->mode();
        if ($mode !== 'image') {
            return;
        }

        $w = $this->opts();

        $posMap = [
            'top-left' => 'topleft',
            'top-right' => 'topright',
            'bottom-left' => 'bottomleft',
            'bottom-right' => 'bottomright',
        ];

        // Coconut doesn't support sizing/opacity in the API (usually).
        // Best practice: upload a PNG already sized/with alpha if needed.
        $jobData['outputs']['mp4'][0]['watermark'] = [
            'url' => $this->watermarkPath(),
            'position' => $posMap[$w['position']] ?? 'bottomright',
        ];
    }
}
