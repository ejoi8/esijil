<?php

namespace App\Support;

class Nokp
{
    /**
     * Normalise a NOKP (Malaysian IC) to digits only. Single source of truth so
     * validation, queries, and rate-limit keys can never disagree.
     */
    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }
}
