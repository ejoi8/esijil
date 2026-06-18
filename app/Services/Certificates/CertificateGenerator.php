<?php

namespace App\Services\Certificates;

/**
 * Turns a (font-normalized) pdfme template + its resolved inputs into raw PDF
 * bytes. Implementations are the interchangeable render engines; the active one
 * is chosen from CertificateSettings (see AppServiceProvider). Add a new engine
 * by implementing this interface, adding a CertificatePdfRenderer case, and
 * registering it in the binding — no change to PdfmeCertificateRenderer.
 */
interface CertificateGenerator
{
    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, string>  $inputs
     */
    public function generate(array $template, array $inputs): string;
}
