<?php

namespace Spatie\ModelStatus\Exceptions;

use Exception;

class InvalidSetting extends Exception
{
    public static function create(): self
    {
        return new self("Bad status setting");
    }
}
