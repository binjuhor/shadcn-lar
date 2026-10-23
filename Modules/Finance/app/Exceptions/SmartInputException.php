<?php

namespace Modules\Finance\Exceptions;

use RuntimeException;

/**
 * User-facing Smart Input failure (unsupported provider, unparseable input, invalid ownership).
 * The message is safe to return to the client.
 */
class SmartInputException extends RuntimeException {}
