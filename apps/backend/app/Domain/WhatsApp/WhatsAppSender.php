<?php

namespace App\Domain\WhatsApp;

use App\Domain\Order\Models\Order;
use App\Domain\Order\Support\OrderPlaceholders;
use App\Domain\WhatsApp\Contracts\WhatsAppGateway;
use App\Domain\WhatsApp\Jobs\SendWhatsApp;
use App\Domain\WhatsApp\Models\WhatsAppMessage;
use App\Settings\WhatsAppSettings;

/** Fills in template parameters and queues WhatsApp messages; every one is recorded in `whatsapp_messages`. */
final class WhatsAppSender
{
    public function __construct(private WhatsAppSettings $settings) {}

    public function notifyCustomer(string $event, Order $order): void
    {
        if ($template = $this->template($event)) {
            $this->queue($order->customer_phone, $template, $event, $order);
        }
    }

    public function notifyAdmins(string $event, Order $order): void
    {
        if ($template = $this->template($event)) {
            foreach ($this->settings->adminPhones() as $phone) {
                $this->queue($phone, $template, $event, $order);
            }
        }
    }

    public function gateway(): WhatsAppGateway
    {
        return app(config('whatsapp.gateways.'.$this->settings->gateway) ?? config('whatsapp.gateways.log'));
    }

    /**
     * The placeholders in the preview text, in the order they appear, become the template's {{1}}, {{2}}….
     *
     * @return list<string>
     */
    public static function params(string $body, Order $order): array
    {
        preg_match_all('/\{[a-z_]+\}/', $body, $found);
        $values = OrderPlaceholders::for($order);

        return array_map(fn (string $p) => $values[$p] ?? '', $found[0]);
    }

    /** @param  array{template: string, language: string, body: string}  $template */
    private function queue(string $to, array $template, string $event, Order $order): void
    {
        $message = WhatsAppMessage::create([
            'to' => $to,
            'template' => $template['template'],
            'language' => $template['language'],
            'params' => self::params($template['body'], $order),
            'preview' => strtr($template['body'], OrderPlaceholders::for($order)),
            'event' => $event,
            'order_id' => $order->id,
            'gateway' => $this->settings->gateway,
        ]);

        SendWhatsApp::dispatch($message->id)->afterCommit();
    }

    /** @return array{template: string, language: string, body: string}|null */
    private function template(string $event): ?array
    {
        $template = $this->settings->enabled ? ($this->settings->templates[$event] ?? null) : null;

        return ($template['enabled'] ?? false) && filled($template['template'] ?? null)
            ? ['template' => $template['template'], 'language' => $template['language'] ?? 'bn', 'body' => (string) ($template['body'] ?? '')]
            : null;
    }
}
