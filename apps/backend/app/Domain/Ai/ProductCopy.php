<?php

namespace App\Domain\Ai;

/** Product copy in both languages, as the AI wrote it. Descriptions are sanitized HTML. */
final readonly class ProductCopy
{
    private const TAGS = '<p><ul><ol><li><strong><em><h3><br>';

    public function __construct(
        public string $shortDescription,
        public string $description,
        public string $shortDescriptionEn,
        public string $descriptionEn,
        public string $metaTitle,
        public string $metaDescription,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $text = fn (string $key, int $max = 2000) => mb_substr(trim(strip_tags((string) ($data[$key] ?? ''))), 0, $max);
        $html = fn (string $key) => trim(strip_tags((string) ($data[$key] ?? ''), self::TAGS));

        return new self(
            shortDescription: $text('short_description'),
            description: $html('description_html'),
            shortDescriptionEn: $text('short_description_en'),
            descriptionEn: $html('description_en_html'),
            metaTitle: $text('meta_title', 70),
            metaDescription: $text('meta_description', 160),
        );
    }
}
