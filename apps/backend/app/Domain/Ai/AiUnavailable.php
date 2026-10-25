<?php

namespace App\Domain\Ai;

use RuntimeException;

/** The provider couldn't write: not configured, key rejected, refused or unreachable. The message is shown to staff. */
class AiUnavailable extends RuntimeException {}
