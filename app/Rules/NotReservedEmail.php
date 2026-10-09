<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** The guest account's domain can't be used for a real account (registration or admin). */
class NotReservedEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && User::isReservedEmail($value)) {
            $fail('This email address is reserved. Please use your own address.');
        }
    }
}
