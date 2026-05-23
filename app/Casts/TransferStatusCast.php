<?php

namespace App\Casts;

use App\Enums\TransferStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use InvalidArgumentException;

class TransferStatusCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        try {
            return TransferStatus::fromValue($value);
        } catch (\ValueError $exception) {
            throw new InvalidArgumentException(
                "Invalid TransferStatus value [{$value}] for attribute {$key}."
            );
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof TransferStatus) {
            return $value->value;
        }

        try {
            return TransferStatus::fromValue($value)->value;
        } catch (\ValueError $exception) {
            throw new InvalidArgumentException(
                "Invalid TransferStatus value [{$value}] for attribute {$key}."
            );
        }
    }
}
