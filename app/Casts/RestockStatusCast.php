<?php

namespace App\Casts;

use App\Enums\RestockStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use InvalidArgumentException;

class RestockStatusCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        try {
            return RestockStatus::fromValue($value);
        } catch (\ValueError $exception) {
            throw new InvalidArgumentException(
                "Invalid RestockStatus value [{$value}] for attribute {$key}."
            );
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof RestockStatus) {
            return $value->value;
        }

        try {
            return RestockStatus::fromValue($value)->value;
        } catch (\ValueError $exception) {
            throw new InvalidArgumentException(
                "Invalid RestockStatus value [{$value}] for attribute {$key}."
            );
        }
    }
}
