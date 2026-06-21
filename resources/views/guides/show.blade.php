@php
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $guide['title'],
        'description' => $guide['description'],
        'inLanguage' => 'ms-MY',
        'dateModified' => $guide['updated'],
        'mainEntityOfPage' => route('guides.show', $guide['slug']),
        'publisher' => ['@type' => 'Organization', 'name' => config('app.name')],
    ];
@endphp

<x-layouts.mono
    :title="$guide['title']"
    :description="$guide['description']"
    :canonical="route('guides.show', $guide['slug'])"
>
    <div class="col">
        <p class="kicker"><a href="{{ route('guides.index') }}">Panduan</a></p>
        <h1>{{ $guide['title'] }}</h1>

        <div class="stack">
            <article class="card">
                @include('guides.content.'.$guide['slug'])
            </article>
        </div>
    </div>

    <x-json-ld :data="$jsonLd" />
</x-layouts.mono>
