<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Enums\BannerPlacement;
use App\Domain\Content\Models\Banner;
use App\Domain\Content\Support\HomeSections;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BannerResource;
use App\Http\Resources\V1\HomeSectionResource;

/** Everything the home page needs in one request, in the order set in the homepage builder. */
class HomeController extends Controller
{
    public function __invoke(HomeSections $sections): array
    {
        $banners = Banner::live()->orderBy('sort_order')->get();

        return [
            'banners' => [
                'hero' => BannerResource::collection($banners->where('placement', BannerPlacement::Hero)->values()),
                'promo' => BannerResource::collection($banners->where('placement', BannerPlacement::Promo)->values()),
            ],
            'sections' => HomeSectionResource::collection($sections->resolve()),
        ];
    }
}
