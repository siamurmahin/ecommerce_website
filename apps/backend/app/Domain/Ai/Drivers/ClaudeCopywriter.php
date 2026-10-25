<?php

namespace App\Domain\Ai\Drivers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Domain\Ai\AiUnavailable;
use App\Domain\Ai\Contracts\Copywriter;
use App\Domain\Ai\ProductCopy;
use App\Settings\AiSettings;

/** Claude via the official Anthropic SDK, with structured output so the reply is always valid JSON. */
class ClaudeCopywriter implements Copywriter
{
    private const SYSTEM = <<<'TXT'
        You write product copy for an online shop in Bangladesh that sells Islamic-lifestyle goods: panjabi, borka/abaya, hijab, attar, prayer mats, books, Sunnah foods and gifts. Customers are mostly on phones and often order cash on delivery.

        Write the Bangla in natural, everyday Bangladeshi Bangla, the way a good local shop writes, not a translation of English. Write the English separately for English-speaking visitors; it doesn't need to mirror the Bangla.

        Use only the facts you are given. Never invent materials, sizes, weights, origins, certifications or offers; if something isn't given, leave it out. No health or medical claims, no religious rulings, no exaggeration ("best in Bangladesh", "100% guaranteed"). Respectful, warm, plain.

        Fields:
        - short_description / short_description_en: 3 to 5 short highlights, one per line, plain text, no bullet characters.
        - description_html / description_en_html: 2 to 4 short paragraphs, may include one <ul> of key details. Only <p>, <ul>, <li>, <strong>, <em>, <h3>.
        - meta_title: Bangla, at most 60 characters, product name first.
        - meta_description: Bangla, at most 155 characters, a reason to click.
        TXT;

    public function __construct(private AiSettings $settings) {}

    public function productCopy(array $facts): ProductCopy
    {
        if (! $this->settings->ready()) {
            throw new AiUnavailable('AI লেখা চালু নেই বা API key দেওয়া হয়নি (সেটিংস → AI লেখা)।');
        }

        $system = self::SYSTEM;
        if (filled($this->settings->brand_voice)) {
            $system .= "\n\nShop's own style notes:\n".$this->settings->brand_voice;
        }

        try {
            $message = $this->client()->beta->messages->create(
                model: $this->settings->model,
                maxTokens: 16000,
                system: $system,
                messages: [['role' => 'user', 'content' => $this->brief($facts)]],
                outputConfig: ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => $this->schema()]],
                // A declined request is retried on Anthropic's recommended fallback model instead of failing.
                betas: ['server-side-fallback-2026-07-01'],
                fallbacks: 'default',
            );
        } catch (AuthenticationException|PermissionDeniedException) {
            throw new AiUnavailable('API key ঠিক নেই — সেটিংস → AI লেখা থেকে নতুন key দিন।');
        } catch (RateLimitException) {
            throw new AiUnavailable('এখন অনেক অনুরোধ যাচ্ছে, এক মিনিট পরে আবার চেষ্টা করুন।');
        } catch (APIConnectionException) {
            throw new AiUnavailable('AI সার্ভারে পৌঁছানো যায়নি, একটু পরে আবার চেষ্টা করুন।');
        } catch (APIStatusException $e) {
            report($e);
            throw new AiUnavailable('AI এখন লিখতে পারছে না, একটু পরে আবার চেষ্টা করুন।');
        }

        if ($message->stopReason === 'refusal') {
            throw new AiUnavailable('AI এই পণ্যের বিবরণ লিখতে রাজি হয়নি — তথ্যগুলো একটু বদলে আবার চেষ্টা করুন।');
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text' && is_array($data = json_decode($block->text, true))) {
                return ProductCopy::fromArray($data);
            }
        }

        throw new AiUnavailable('AI-র উত্তর পড়া যায়নি, আবার চেষ্টা করুন।');
    }

    protected function client(): Client
    {
        return new Client(apiKey: $this->settings->api_key, requestOptions: ['timeout' => 120, 'maxRetries' => 2]);
    }

    /** @param  array<string, mixed>  $facts */
    private function brief(array $facts): string
    {
        $lines = ["Product name (Bangla): {$facts['name']}"];
        if (filled($facts['name_en'])) {
            $lines[] = "Product name (English): {$facts['name_en']}";
        }
        if (filled($facts['category'])) {
            $lines[] = "Category: {$facts['category']}";
        }
        if ($facts['price']) {
            $lines[] = 'Price: ৳'.number_format($facts['price'] / 100);
        }
        foreach ($facts['specifications'] as $spec) {
            $lines[] = "{$spec['label']}: {$spec['value']}";
        }
        if (filled($facts['notes'])) {
            $lines[] = "\nNotes from the shop:\n{$facts['notes']}";
        }
        if (filled($facts['current'])) {
            $lines[] = "\nCurrent description (improve on it, keep its facts):\n".strip_tags($facts['current']);
        }

        return implode("\n", $lines);
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        $fields = ['short_description', 'description_html', 'short_description_en', 'description_en_html', 'meta_title', 'meta_description'];

        return [
            'type' => 'object',
            'properties' => array_fill_keys($fields, ['type' => 'string']),
            'required' => $fields,
            'additionalProperties' => false,
        ];
    }
}
