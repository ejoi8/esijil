<x-filament-panels::page>
    @once
        @vite(['resources/css/app.css', 'resources/js/certificate-template-designer.js'])
    @endonce

    @php
        $fontManifest = app(\App\Services\Certificates\PdfmeFontRegistry::class)->forDesigner();
    @endphp

    <div
        class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]"
        data-livewire-id="{{ $this->getId() }}"
        data-pdfme-root
    >
        <div class="space-y-6">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="space-y-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Designer Controls</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Add, move, resize, and rename placeholders directly on the page canvas.
                        </p>
                        <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">
                            Add an image field on the canvas, then click it to upload a logo, signature, or background directly into the template.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-primary-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:cursor-not-allowed disabled:opacity-60"
                            data-pdfme-save
                        >
                            Save Layout
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                            data-pdfme-reset
                        >
                            Reset To Default
                        </button>
                    </div>

                    <div class="rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm text-gray-600 dark:border-white/10 dark:text-gray-400">
                        <span class="font-medium text-gray-800 dark:text-gray-200">Status:</span>
                        <span data-pdfme-status>Ready.</span>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="space-y-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Insert Fields</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Click any field to drop it on the canvas, then drag it into place. Text fields fill with live data at render; image fields hold a logo/signature you upload (the QR fills automatically).
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-white">Text Placeholders</h3>

                            <div class="grid gap-2">
                                @foreach (\App\Services\Certificates\CertificateVariables::text() as $token => $description)
                                    <button
                                        type="button"
                                        data-pdfme-insert="text"
                                        data-pdfme-token="{{ $token }}"
                                        class="group flex w-full items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-left transition hover:border-primary-400 hover:bg-primary-50 dark:border-white/10 dark:bg-white/5 dark:hover:bg-primary-500/10"
                                    >
                                        <span class="min-w-0">
                                            <span class="block font-mono text-xs font-semibold text-primary-700 dark:text-primary-300">{!! '&#123;&#123;' . e($token) . '&#125;&#125;' !!}</span>
                                            <span class="mt-0.5 block truncate text-xs text-gray-600 dark:text-gray-400">{{ $description }}</span>
                                        </span>
                                        <span class="shrink-0 text-xs font-semibold text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-300">+ Add</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-2">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-white">Image Field Names</h3>

                            <div class="grid gap-2">
                                @foreach (\App\Services\Certificates\CertificateVariables::images() as $fieldName => $description)
                                    <button
                                        type="button"
                                        data-pdfme-insert="image"
                                        data-pdfme-name="{{ $fieldName }}"
                                        class="group flex w-full items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-left transition hover:border-emerald-400 hover:bg-emerald-50 dark:border-white/10 dark:bg-white/5 dark:hover:bg-emerald-500/10"
                                    >
                                        <span class="min-w-0">
                                            <span class="block font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-300">{{ $fieldName }}</span>
                                            <span class="mt-0.5 block truncate text-xs text-gray-600 dark:text-gray-400">{{ $description }}</span>
                                        </span>
                                        <span class="shrink-0 text-xs font-semibold text-gray-400 group-hover:text-emerald-600 dark:group-hover:text-emerald-300">+ Add</span>
                                    </button>
                                @endforeach
                            </div>

                            <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                                Add these as <span class="font-medium">image</span> fields on the canvas, then upload the actual image directly into the selected field.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-500/20 dark:bg-amber-500/10">
                <div class="space-y-2">
                    <h2 class="text-sm font-semibold text-amber-950 dark:text-amber-100">How It Works</h2>
                    <p class="text-sm text-amber-900/80 dark:text-amber-100/80">
                        The designer layout is now the active certificate output. Uploaded images are saved inside the template itself, so new downloads use the latest layout directly.
                    </p>
                </div>
            </section>
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-3 dark:border-white/10">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Canvas</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Drag fields freely on the A4 page, then save the layout once it is stable.
                </p>
            </div>

            <div class="bg-gray-100/80 p-4 dark:bg-gray-950/70">
                <div
                    class="h-[85vh] min-h-[900px] w-full overflow-hidden rounded-xl border border-gray-300 bg-white shadow-inner dark:border-white/10 dark:bg-gray-950"
                    data-pdfme-canvas
                    wire:ignore
                ></div>
            </div>
        </section>

        <script type="application/json" data-pdfme-template>@json($templateData)</script>
        <script type="application/json" data-pdfme-default-template>@json($defaultTemplateData)</script>
        <script type="application/json" data-pdfme-fonts>@json($fontManifest)</script>
    </div>
</x-filament-panels::page>
