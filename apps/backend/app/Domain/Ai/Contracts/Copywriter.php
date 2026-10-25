<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\AiUnavailable;
use App\Domain\Ai\ProductCopy;

interface Copywriter
{
    /**
     * Writes product copy from what staff already entered.
     *
     * @param  array{name: string, name_en: ?string, category: ?string, price: ?int, specifications: array<int, array{label: string, value: string}>, notes: ?string, current: ?string}  $facts
     *
     * @throws AiUnavailable
     */
    public function productCopy(array $facts): ProductCopy;
}
