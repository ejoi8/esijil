<?php

namespace App\Support;

/**
 * Registry for the Bahasa-Melayu content hub (/panduan). Each entry maps a slug
 * to its metadata; the body lives in resources/views/guides/content/{slug}.blade.php.
 * Add a guide by adding an entry here plus its content view — no DB needed.
 */
class Guides
{
    /**
     * @return array<string, array{title: string, description: string, updated: string}>
     */
    public static function all(): array
    {
        return [
            'cara-buat-sijil-digital-ber-qr' => [
                'title' => 'Cara buat sijil digital ber-QR untuk program anda',
                'description' => 'Panduan ringkas menjana sijil digital bernombor unik yang boleh disemak peserta secara dalam talian.',
                'updated' => '2026-06-21',
            ],
            'panduan-pendaftaran-peserta-program' => [
                'title' => 'Panduan pendaftaran peserta program dalam talian',
                'description' => 'Cara menyediakan borang pendaftaran, mengumpul medan tersuai dan menghadkan tempat untuk acara atau bengkel anda.',
                'updated' => '2026-06-21',
            ],
            'sistem-kehadiran-qr-untuk-acara' => [
                'title' => 'Sistem kehadiran QR untuk acara & persatuan',
                'description' => 'Cara merekod kehadiran peserta dengan imbasan kod QR di pintu — termasuk mod semak dan mod laju.',
                'updated' => '2026-06-21',
            ],
        ];
    }

    /**
     * @return array{slug: string, title: string, description: string, updated: string}|null
     */
    public static function find(string $slug): ?array
    {
        $guide = static::all()[$slug] ?? null;

        return $guide === null ? null : array_merge(['slug' => $slug], $guide);
    }
}
