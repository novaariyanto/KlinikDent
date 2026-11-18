<?php

namespace App\Support\Cms;

final class EditorialGuard
{
    /**
     * Frasa yang tidak boleh masuk artikel published/scheduled.
     *
     * @return list<string>
     */
    public static function forbiddenPhrases(): array
    {
        return [
            'Draft ini belum dipublikasikan',
            'Slug target',
            'instruksi internal penulis',
            'TODO editorial',
            '[placeholder]',
            'lorem ipsum',
        ];
    }

    public static function findIn(?string $html): ?string
    {
        $haystack = mb_strtolower(strip_tags((string) $html));

        foreach (self::forbiddenPhrases() as $phrase) {
            if ($phrase !== '' && str_contains($haystack, mb_strtolower($phrase))) {
                return $phrase;
            }
        }

        return null;
    }

    public static function assertPublishable(?string $html): void
    {
        $hit = self::findIn($html);
        if ($hit !== null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'body' => 'Artikel tidak dapat dipublikasikan karena masih berisi teks editorial: “'.$hit.'”.',
            ]);
        }
    }
}
