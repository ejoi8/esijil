<?php

namespace App\Policies;

class RegistrationPolicy extends ResourcePolicy
{
    protected function prefix(): string
    {
        return 'registration';
    }
}
