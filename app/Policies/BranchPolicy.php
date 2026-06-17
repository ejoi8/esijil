<?php

namespace App\Policies;

class BranchPolicy extends ResourcePolicy
{
    protected function prefix(): string
    {
        return 'branch';
    }
}
