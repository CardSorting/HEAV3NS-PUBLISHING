<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class IconsServiceProvider extends ServiceProvider
{
    public function register() {}

    public function boot() {}

    public static function readIcon($icon, $variant = '', $centered = true, $classes = '')
    {
        if ($variant !== '') {
            $classes .= ' icon-'.$variant;
        }
        if ($centered) {
            $classes .= ' d-flex justify-content-center align-items-center';
        }

        $svg = self::readSvgContent($icon);

        return "<div class=\"ion-icon-wrapper {$classes}\">
            <div class=\"ion-icon-inner\">
                {$svg}
            </div>
        </div>";
    }

    public static function readSvgContent($icon)
    {
        // 1) Ionicons path (do NOT force currentColor here)
        $ionPath = public_path("/libs/ionicons/dist/svg/{$icon}.svg");
        if (file_exists($ionPath)) {
            $content = file_get_contents($ionPath);
            return preg_replace('~<title>.*?</title>~is', '', $content);
        }

        // 2) Custom paths (force currentColor)
        $customPaths = [
            public_path("/img/logos/{$icon}.svg"),
            public_path("/img/icons/{$icon}.svg"),
        ];

        foreach ($customPaths as $path) {
            if (!file_exists($path)) {
                continue;
            }

            $content = file_get_contents($path);

            // remove titles
            $content = preg_replace('~<title>.*?</title>~is', '', $content);

            // strip hardcoded fill/stroke (keep "none")
            $content = preg_replace('~\sfill="(?!none)[^"]*"~i', '', $content);
            $content = preg_replace('~\sstroke="(?!none)[^"]*"~i', '', $content);

            // strip inline style fill/stroke (optional but helpful)
            $content = preg_replace('~\sstyle="[^"]*?(fill|stroke)\s*:\s*[^;"]+;?[^"]*?"~i', '', $content);

            // ensure root svg uses currentColor
            if (preg_match('~<svg\b[^>]*\bfill=~i', $content)) {
                $content = preg_replace('~<svg\b([^>]*)\sfill="[^"]*"~i', '<svg$1 fill="currentColor"', $content, 1);
            } else {
                $content = preg_replace('~<svg\b([^>]*)>~i', '<svg$1 fill="currentColor">', $content, 1);
            }

            return $content;
        }

        throw new \Exception("SVG icon '{$icon}' not found in any known paths.");
    }
}
