<?php

namespace App\Policies;

class CustomFieldPolicy extends ResourcePolicy
{
    protected function prefix(): string
    {
        return 'customField';
    }
}
