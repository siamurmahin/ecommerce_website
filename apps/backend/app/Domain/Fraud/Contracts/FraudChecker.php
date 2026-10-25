<?php

namespace App\Domain\Fraud\Contracts;

use App\Domain\Fraud\FraudReport;

/** Looks up a phone's parcel history. Add one by adding a class and listing it in `config/courier.php`. */
interface FraudChecker
{
    public static function label(): string;

    /** @throws \RuntimeException when the lookup fails */
    public function check(string $phone): FraudReport;
}
