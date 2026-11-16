<?php

namespace App\Support;

/**
 * Shared contact validation rules used across registration, profile,
 * staff management, authentication, and transaction workflows.
 */
final class ContactValidationRules
{
    /**
     * Philippine mobile numbers only: 09XXXXXXXXX or +639XXXXXXXXX.
     */
    public static function philippineMobile(): string
    {
        return 'regex:/^(?:09[0-9]{9}|\+639[0-9]{9})$/';
    }
    
    public static function email(): string
    {
        return 'email:rfc';
    }
}
