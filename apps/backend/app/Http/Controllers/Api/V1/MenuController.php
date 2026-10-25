<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Enums\MenuLocation;
use App\Domain\Content\Models\MenuItem;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MenuItemResource;

class MenuController extends Controller
{
    /** Active menu items grouped by location. */
    public function __invoke(): array
    {
        $items = MenuItem::active()->orderBy('sort_order')->get();
        $at = fn (MenuLocation $location) => MenuItemResource::collection($items->where('location', $location)->values());

        return [
            'header' => $at(MenuLocation::Header),
            'footer_help' => $at(MenuLocation::FooterHelp),
            'footer_company' => $at(MenuLocation::FooterCompany),
        ];
    }
}
