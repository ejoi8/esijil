<?php

namespace App\Services\Certificates;

use App\Exceptions\CertificateRenderingException;
use Symfony\Component\Process\Process;
use Throwable;

class PdfmeNodeCertificateGenerator
{
    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, string>  $inputs
     */
    public function __construct(protected PdfmeFontRegistry $fontRegistry) {}

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, string>  $inputs
     */
    public function generate(array $template, array $inputs): string
    {
        $process = new Process([
            (string) config('certificates.pdfme.node_binary', 'node'),
            base_path('resources/js/certificate-pdf-generator.mjs'),
        ], base_path());

        $process->setTimeout(60);
        $process->setInput(json_encode([
            'template' => $template,
            'inputs' => $inputs,
            'fonts' => $this->fontRegistry->definitions(),
        ], JSON_THROW_ON_ERROR));

        try {
            $process->mustRun();
        } catch (Throwable $exception) {
            $errorOutput = trim($process->getErrorOutput());
            $message = 'Unable to render the certificate with pdfme. Ensure Node.js is installed and working on the server.';

            if ($errorOutput !== '') {
                $message .= ' '.$errorOutput;
            }

            throw CertificateRenderingException::fromGenerator($message, $exception);
        }

        $pdf = base64_decode(trim($process->getOutput()), true);

        if ($pdf === false) {
            throw CertificateRenderingException::fromGenerator('Unable to decode the generated pdfme certificate output.');
        }

        return $pdf;
    }
}
