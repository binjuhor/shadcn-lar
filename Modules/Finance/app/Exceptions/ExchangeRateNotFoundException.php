<?php

namespace Modules\Finance\Exceptions;

use RuntimeException;

/** No stored rate between two currencies; the message is safe to show users. */
class ExchangeRateNotFoundException extends RuntimeException {}
