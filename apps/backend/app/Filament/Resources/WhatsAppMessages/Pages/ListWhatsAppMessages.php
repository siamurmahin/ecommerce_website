<?php

namespace App\Filament\Resources\WhatsAppMessages\Pages;

use App\Filament\Resources\WhatsAppMessages\WhatsAppMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppMessages extends ListRecords
{
    protected static string $resource = WhatsAppMessageResource::class;
}
