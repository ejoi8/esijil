@props(['variant' => 'mark'])

@php
    $iconSize = $variant === 'mini' ? 11 : 14;
    $markClass = $variant === 'mini' ? 'mini-mark' : 'mark';
@endphp

<span class="{{ $markClass }}" aria-hidden="true">
    <svg width="{{ $iconSize }}" height="{{ $iconSize }}" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>
        <path d="M14 3v5h5"/>
    </svg>
</span>
