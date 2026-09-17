<?php

declare(strict_types=1);

namespace Surf4Miles\Sqlite;

/** Thrown when an uploaded file fails strict validation. Message is safe to show to the user. */
final class UploadValidationException extends \RuntimeException
{
}
