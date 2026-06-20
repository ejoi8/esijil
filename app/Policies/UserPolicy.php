<?php

namespace App\Policies;

class UserPolicy extends ResourcePolicy
{
    protected function prefix(): string
    {
        return 'user';
    }
}
