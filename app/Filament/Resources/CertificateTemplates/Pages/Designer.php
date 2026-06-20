<?php

namespace App\Filament\Resources\CertificateTemplates\Pages;

use App\Filament\Resources\CertificateTemplates\CertificateTemplateResource;
use App\Models\CertificateTemplate;
use App\Services\Certificates\PdfmeFontRegistry;
use App\Services\Certificates\PdfmeTemplateFactory;
use App\Services\Certificates\PdfmeTemplateLegacyAssetInliner;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use JsonException;

class Designer extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CertificateTemplateResource::class;

    protected string $view = 'filament.resources.certificate-templates.pages.designer';

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * @var array<string, mixed>
     */
    public array $templateData = [];

    /**
     * @var array<string, mixed>
     */
    public array $defaultTemplateData = [];

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('certificateTemplate.update') ?? false;
    }

    public function mount(int|string $record): void
    {
        abort_unless(static::canAccess(), 403);

        $this->record = $this->resolveRecord($record);
        $this->refreshTemplateData();
    }

    public function saveDesigner(string $template): void
    {
        abort_unless(auth()->user()?->can('certificateTemplate.update') ?? false, 403);

        try {
            $decodedTemplate = json_decode($template, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            Notification::make()
                ->title('Template data could not be parsed.')
                ->danger()
                ->send();

            return;
        }

        if (! is_array($decodedTemplate) || ! array_key_exists('basePdf', $decodedTemplate) || ! array_key_exists('schemas', $decodedTemplate)) {
            Notification::make()
                ->title('Template data is not valid.')
                ->danger()
                ->send();

            return;
        }

        /** @var CertificateTemplate $templateRecord */
        $templateRecord = $this->getRecord();
        $decodedTemplate = $this->templateFactory()->normalizeFullPageCanvas(
            $this->fontRegistry()->normalizeTemplate(
                $this->legacyAssetInliner()->inline($decodedTemplate, is_array($templateRecord->schema) ? $templateRecord->schema : []),
            ),
        );

        $templateRecord->forceFill([
            'pdfme_template' => $decodedTemplate,
        ])->save();

        $this->templateData = $decodedTemplate;

        Notification::make()
            ->title('Designer layout saved.')
            ->success()
            ->send();
    }

    public function getHeading(): string
    {
        return 'Template Designer';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Edit Settings')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url($this->getResourceUrl('edit')),
        ];
    }

    protected function refreshTemplateData(): void
    {
        $legacySchema = is_array($this->getRecord()->schema) ? $this->getRecord()->schema : [];
        $defaultTemplate = $this->legacyAssetInliner()->inline(
            $this->templateFactory()->fromCertificateTemplate($this->getRecord()),
            $legacySchema,
        );
        $currentTemplate = is_array($this->getRecord()->pdfme_template)
            ? $this->getRecord()->pdfme_template
            : $defaultTemplate;
        $baselineTemplate = $this->shouldUseSavedLayoutAsDefault($this->getRecord(), $currentTemplate)
            ? $currentTemplate
            : $defaultTemplate;

        $this->defaultTemplateData = $this->templateFactory()->normalizeFullPageCanvas(
            $this->fontRegistry()->normalizeTemplate(
                $this->legacyAssetInliner()->inline($baselineTemplate, $legacySchema),
            ),
        );
        $this->templateData = $this->templateFactory()->normalizeFullPageCanvas(
            $this->fontRegistry()->normalizeTemplate(
                $this->legacyAssetInliner()->inline($currentTemplate, $legacySchema),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $currentTemplate
     */
    protected function shouldUseSavedLayoutAsDefault(CertificateTemplate $certificateTemplate, array $currentTemplate): bool
    {
        // Once a template has a saved layout, prefer it as the designer baseline.
        return $currentTemplate !== [];
    }

    protected function legacyAssetInliner(): PdfmeTemplateLegacyAssetInliner
    {
        return app(PdfmeTemplateLegacyAssetInliner::class);
    }

    protected function fontRegistry(): PdfmeFontRegistry
    {
        return app(PdfmeFontRegistry::class);
    }

    protected function templateFactory(): PdfmeTemplateFactory
    {
        return app(PdfmeTemplateFactory::class);
    }
}
