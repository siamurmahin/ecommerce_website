<?php

namespace App\Domain\Sms;

use App\Domain\Order\Models\Order;
use App\Domain\Sms\Contracts\SmsGateway;
use App\Domain\Sms\Jobs\SendSms;
use App\Domain\Sms\Models\SmsLog;
use App\Settings\BrandSettings;
use App\Settings\SiteSettings;
use App\Settings\SmsSettings;
use RuntimeException;
use Throwable;

/** Renders templates and queues messages; every message is recorded in `sms_logs`. */
final class SmsSender
{
    public function __construct(private SmsSettings $settings) {}

    /** Customer message for an order event (order_placed, confirmed, shipped…). */
    public function notifyCustomer(string $event, Order $order): void
    {
        if ($body = $this->template($event)) {
            $this->queue($order->customer_phone, $this->render($body, $order), $event, $order);
        }
    }

    public function notifyAdmins(string $event, Order $order): void
    {
        if ($body = $this->template($event)) {
            foreach ($this->settings->adminPhones() as $phone) {
                $this->queue($phone, $this->render($body, $order), $event, $order);
            }
        }
    }

    public function queue(string $to, string $message, ?string $event = null, ?Order $order = null): SmsLog
    {
        $log = SmsLog::create([
            'to' => $to,
            'message' => $message,
            'event' => $event,
            'order_id' => $order?->id,
            'gateway' => $this->settings->gateway,
        ]);

        SendSms::dispatch($log->id)->afterCommit();

        return $log;
    }

    /**
     * Sends right away, for messages that can't wait for the queue (login codes). The log stores
     * `$logged` instead of the real text.
     *
     * @throws RuntimeException when the gateway fails
     */
    public function sendNow(string $to, string $message, string $event, string $logged): void
    {
        $log = SmsLog::create(['to' => $to, 'message' => $logged, 'event' => $event, 'gateway' => $this->settings->gateway]);

        try {
            $this->gateway()->send($to, $message);
            $log->update(['status' => 'sent']);
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);

            throw new RuntimeException('SMS পাঠানো যায়নি।', previous: $e);
        }
    }

    public function gateway(): SmsGateway
    {
        $class = config('sms.gateways.'.$this->settings->gateway) ?? config('sms.gateways.log');

        return app($class);
    }

    public function render(string $body, Order $order): string
    {
        $site = app(SiteSettings::class);
        $total = '৳'.number_format($order->total / 100, $order->total % 100 ? 2 : 0);
        $track = rtrim((string) config('shop.storefront_url'), '/').'/track-order';

        return strtr($body, [
            '{name}' => $order->customer_name,
            '{order}' => $order->number,
            '{total}' => $total,
            '{phone}' => $order->customer_phone,
            '{shop}' => app(BrandSettings::class)->name,
            '{hotline}' => $site->hotline,
            '{track_url}' => $track,
        ]);
    }

    private function template(string $event): ?string
    {
        if (! $this->settings->enabled) {
            return null;
        }

        $template = $this->settings->templates[$event] ?? null;

        return ($template['enabled'] ?? false) && filled($template['body'] ?? null) ? $template['body'] : null;
    }
}
