<?php

namespace App\Domain\Marketing\Support;

use App\Domain\Marketing\Models\LandingPage;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a landing page's builder blocks into one flat, render-ready shape: image paths become URLs,
 * rich text becomes HTML, video links become embed URLs. Hidden or empty blocks are dropped, and an
 * order form is appended when the page has a product but no form block.
 */
final class LandingBlocks
{
    public const TYPES = ['hero', 'benefits', 'gallery', 'video', 'text', 'reviews', 'faq', 'cta', 'order_form'];

    /** @return list<array<string, mixed>> */
    public static function resolve(LandingPage $page): array
    {
        $blocks = [];

        foreach ($page->blocks ?? [] as $i => $block) {
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? [];

            if (! in_array($type, self::TYPES, true) || ! ($data['enabled'] ?? true)) {
                continue;
            }

            $resolved = [
                'id' => "{$i}-{$type}",
                'type' => $type,
                'title' => self::text($data['title'] ?? null),
                'subtitle' => self::text($data['subtitle'] ?? null),
                'badge' => self::text($data['badge'] ?? null),
                'button_label' => self::text($data['button_label'] ?? null),
                'content' => filled($data['content'] ?? null) ? RichContentRenderer::make($data['content'])->toHtml() : null,
                'image' => self::url($data['image'] ?? null),
                'images' => collect($data['images'] ?? [])->map(fn ($path) => self::url($path))->filter()->values()->all(),
                'video_url' => self::embed($data['url'] ?? null),
                'ends_at' => self::endsAt($data['ends_at'] ?? null),
                'show_price' => (bool) ($data['show_price'] ?? true),
                'items' => collect($data['items'] ?? [])->map(fn (array $item) => [
                    'title' => (string) ($item['title'] ?? $item['name'] ?? $item['question'] ?? ''),
                    'text' => self::text($item['note'] ?? $item['text'] ?? $item['answer'] ?? null),
                    'meta' => self::text($item['location'] ?? null),
                    'icon' => $item['icon'] ?? null,
                    'rating' => isset($item['rating']) ? max(1, min(5, (int) $item['rating'])) : null,
                    'image' => self::url($item['image'] ?? null),
                ])->filter(fn (array $item) => $item['title'] !== '' || $item['image'])->values()->all(),
            ];

            $empty = match ($type) {
                'gallery' => $resolved['images'] === [],
                'video' => $resolved['video_url'] === null,
                'text' => $resolved['content'] === null,
                'benefits', 'reviews', 'faq' => $resolved['items'] === [],
                default => false,
            };

            if (! $empty) {
                $blocks[] = $resolved;
            }
        }

        if ($page->product_id && ! in_array('order_form', array_column($blocks, 'type'), true)) {
            $blocks[] = [
                'id' => 'order-form', 'type' => 'order_form', 'title' => null, 'subtitle' => null, 'badge' => null,
                'button_label' => null, 'content' => null, 'image' => null, 'images' => [], 'video_url' => null,
                'ends_at' => null, 'show_price' => true, 'items' => [],
            ];
        }

        return $blocks;
    }

    /** YouTube or Facebook video link → privacy-friendly embed URL; anything else is dropped. */
    public static function embed(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([\w-]{11})~', $url, $m)) {
            return "https://www.youtube-nocookie.com/embed/{$m[1]}?rel=0";
        }

        if (preg_match('~^https?://(?:www\.|m\.|web\.)?(?:facebook\.com|fb\.watch)/~', $url)) {
            return 'https://www.facebook.com/plugins/video.php?show_text=false&href='.urlencode($url);
        }

        return null;
    }

    private static function endsAt(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $at = Carbon::parse($value);

        // A finished countdown is just noise on the page.
        return $at->isFuture() ? $at->toIso8601String() : null;
    }

    private static function url(mixed $path): ?string
    {
        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    private static function text(mixed $value): ?string
    {
        return filled($value) ? (string) $value : null;
    }
}
