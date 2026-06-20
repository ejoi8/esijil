<?php

namespace App\Policies;

class CertificateTemplatePolicy extends ResourcePolicy
{
    protected function prefix(): string
    {
        return 'certificateTemplate';
    }
}
