<?php

namespace Mi\AgendaTimeline\Tests\Unit;

use Flarum\Foundation\ValidationException;
use Mi\AgendaTimeline\Support\EventTaxonomyValidator;
use PHPUnit\Framework\TestCase;

class EventTaxonomyValidatorTest extends TestCase
{
    private const REF_YEAR = 2026;

    public function test_accepts_valid_payload(): void
    {
        $this->expectNotToPerformAssertions();
        EventTaxonomyValidator::validate([
            ['slug' => 'jour', 'term' => '15'],
            ['slug' => 'mois', 'term' => 'Juin'],
            ['slug' => 'annee', 'term' => '2026'],
            ['slug' => 'ville', 'term' => 'Lyon'],
            ['slug' => 'lieu', 'term' => 'Le Périscope'],
            ['slug' => 'personne', 'term' => 'Mopcut'],
        ], self::REF_YEAR);
    }

    public function test_accepts_empty_payload(): void
    {
        $this->expectNotToPerformAssertions();
        EventTaxonomyValidator::validate([], self::REF_YEAR);
    }

    public function test_accepts_unknown_slug_silently(): void
    {
        $this->expectNotToPerformAssertions();
        EventTaxonomyValidator::validate([
            ['slug' => 'genre', 'term' => 'whatever-we-do-not-know'],
        ], self::REF_YEAR);
    }

    /** @dataProvider invalidMonths */
    public function test_rejects_invalid_month(string $month): void
    {
        $this->expectException(ValidationException::class);
        EventTaxonomyValidator::validate([
            ['slug' => 'mois', 'term' => $month],
        ], self::REF_YEAR);
    }

    public static function invalidMonths(): array
    {
        return [
            'english' => ['June'],
            'lowercase' => ['juin'],
            'typo' => ['Juiin'],
            'empty' => [''],
        ];
    }

    /** @dataProvider invalidDays */
    public function test_rejects_invalid_day(string $day): void
    {
        $this->expectException(ValidationException::class);
        EventTaxonomyValidator::validate([
            ['slug' => 'jour', 'term' => $day],
        ], self::REF_YEAR);
    }

    public static function invalidDays(): array
    {
        return [
            'zero' => ['0'],
            'thirty_two' => ['32'],
            'negative' => ['-1'],
            'non_numeric' => ['abc'],
            'empty' => [''],
        ];
    }

    /** @dataProvider invalidYears */
    public function test_rejects_invalid_year(string $year): void
    {
        $this->expectException(ValidationException::class);
        EventTaxonomyValidator::validate([
            ['slug' => 'annee', 'term' => $year],
        ], self::REF_YEAR);
    }

    public static function invalidYears(): array
    {
        return [
            'too_old' => ['2020'],   // ref 2026 - 5 = 2021 min
            'too_far' => ['2050'],   // ref 2026 + 20 = 2046 max
            'non_numeric' => ['twenty-twenty-six'],
            'negative' => ['-2026'],
        ];
    }

    public function test_accepts_year_at_boundaries(): void
    {
        $this->expectNotToPerformAssertions();
        EventTaxonomyValidator::validate([
            ['slug' => 'annee', 'term' => '2021'],
        ], self::REF_YEAR);
        EventTaxonomyValidator::validate([
            ['slug' => 'annee', 'term' => '2046'],
        ], self::REF_YEAR);
    }

    public function test_rejects_empty_freeform_value(): void
    {
        $this->expectException(ValidationException::class);
        EventTaxonomyValidator::validate([
            ['slug' => 'ville', 'term' => '   '],
        ], self::REF_YEAR);
    }

    public function test_rejects_freeform_value_longer_than_120_chars(): void
    {
        $this->expectException(ValidationException::class);
        EventTaxonomyValidator::validate([
            ['slug' => 'lieu', 'term' => str_repeat('a', 121)],
        ], self::REF_YEAR);
    }

    public function test_accepts_freeform_value_at_120_chars(): void
    {
        $this->expectNotToPerformAssertions();
        EventTaxonomyValidator::validate([
            ['slug' => 'personne', 'term' => str_repeat('a', 120)],
        ], self::REF_YEAR);
    }
}
