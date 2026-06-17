<?php

namespace App\Policies;

class ParticipantPolicy extends ResourcePolicy
{
    protected function prefix(): string
    {
        return 'participant';
    }
}
