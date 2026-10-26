<?php

namespace App\Filament\Support;

use App\Domain\Marketing\Support\LandingTemplates;
use Illuminate\Support\HtmlString;

/**
 * Preview screenshots for the landing page design and block pickers, from public/images/landing-previews
 * (made by `pnpm landing:previews` in the storefront). A missing image just leaves the text.
 */
final class LandingPreview
{
    private const DIR = 'images/landing-previews';

    /** Design radio label: screenshot of the design's top, then its name. */
    public static function design(string $key): HtmlString
    {
        return new HtmlString(
            '<span style="display:flex;flex-direction:column;gap:.5rem">'
            .self::image("designs/{$key}.jpg", '4 / 5')
            .'<span>'.e(LandingTemplates::TEMPLATES[$key]['label'] ?? $key).'</span></span>'
        );
    }

    /** Block picker entry: screenshot, name and one line on what the block is for. */
    public static function block(string $type, string $label, string $description): HtmlString
    {
        return new HtmlString(
            '<span style="display:flex;flex-direction:column;gap:.35rem;white-space:normal;padding:.15rem 0">'
            .self::image("blocks/{$type}.jpg", '16 / 9')
            .'<span style="font-weight:600">'.e($label).'</span>'
            .'<span style="font-size:.75rem;line-height:1.1rem;color:rgb(107 114 128)">'.e($description).'</span></span>'
        );
    }

    private static function image(string $path, string $ratio): string
    {
        $file = public_path(self::DIR.'/'.$path);
        if (! is_file($file)) {
            return '';
        }

        // The file time busts the browser cache when the screenshots are retaken.
        $url = asset(self::DIR.'/'.$path).'?v='.filemtime($file);

        return '<img src="'.e($url).'" alt="" loading="lazy" style="display:block;width:100%;aspect-ratio:'.$ratio.';object-fit:cover;object-position:'.(str_starts_with($path, 'blocks/trust') ? 'bottom' : 'top').';border-radius:.5rem;border:1px solid rgb(0 0 0 / .08);background:#E3E3DF">';
    }
}
