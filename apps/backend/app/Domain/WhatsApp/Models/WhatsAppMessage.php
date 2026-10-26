<?php

namespace App\Domain\WhatsApp\Models;

use App\Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['to', 'template', 'language', 'params', 'preview', 'event', 'order_id', 'gateway', 'status', 'error'])]
class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected function casts(): array
    {
        return ['params' => 'array'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
