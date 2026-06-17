<?php

namespace App\Policies;

class EventPolicy extends ResourcePolicy
{
    protected function prefix(): string
    {
        return 'event';
    }
}
