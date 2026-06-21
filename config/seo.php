<?php

return [
    // Show a tasteful "powered by <app>" referral link on public participant
    // pages — a free-growth touchpoint. Set SEO_REFERRAL_CTA=false to hide it.
    'referral_cta' => (bool) env('SEO_REFERRAL_CTA', true),
];
