<?php

namespace App\Domain\Courier;

use RuntimeException;

/** A courier rejected a request; the message is safe to show staff. */
final class CourierException extends RuntimeException {}
