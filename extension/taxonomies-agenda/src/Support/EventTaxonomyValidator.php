<?php

namespace Mi\AgendaTimeline\Support;

use Flarum\Foundation\ValidationException;
use Illuminate\Support\Arr;

/**
 * Pure validator for event taxonomy payloads coming from the composer.
 *
 * Why: extracting this out of SaveEventTaxonomies keeps validation free of DB
 * and Flarum lifecycle concerns so it can be unit-tested in isolation.
 */
class EventTaxonomyValidator
{
    public const VALID_MONTHS = [
        'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
    ];

    public const YEAR_MIN_OFFSET = -5;
    public const YEAR_MAX_OFFSET = 20;

    public const FREEFORM_SLUGS = ['ville', 'lieu', 'personne'];
    public const FREEFORM_MAX_LENGTH = 120;

    /**
     * @param array<int, array{slug?: string, term?: string}> $taxonomies
     *
     * @throws ValidationException
     */
    public static function validate(array $taxonomies, ?int $referenceYear = null): void
    {
        $referenceYear ??= (int) date('Y');
        $minYear = $referenceYear + self::YEAR_MIN_OFFSET;
        $maxYear = $referenceYear + self::YEAR_MAX_OFFSET;

        foreach ($taxonomies as $item) {
            $slug = Arr::get($item, 'slug');
            $term = trim((string) Arr::get($item, 'term', ''));

            if ($slug === 'annee') {
                if (!ctype_digit($term) || (int) $term < $minYear || (int) $term > $maxYear) {
                    throw new ValidationException([
                        'eventDate' => "Année invalide (attendu $minYear-$maxYear).",
                    ]);
                }
            } elseif ($slug === 'mois') {
                if (!in_array($term, self::VALID_MONTHS, true)) {
                    throw new ValidationException(['eventDate' => 'Mois invalide.']);
                }
            } elseif ($slug === 'jour') {
                if (!ctype_digit($term) || (int) $term < 1 || (int) $term > 31) {
                    throw new ValidationException(['eventDate' => 'Jour invalide (1-31).']);
                }
            } elseif (in_array($slug, self::FREEFORM_SLUGS, true)) {
                if ($term === '' || mb_strlen($term) > self::FREEFORM_MAX_LENGTH) {
                    throw new ValidationException([
                        $slug => "Valeur invalide pour $slug (1-".self::FREEFORM_MAX_LENGTH.' caractères).',
                    ]);
                }
            }
        }
    }
}
