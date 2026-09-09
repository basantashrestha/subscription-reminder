<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/helpers.php';

final class HelpersTest extends TestCase
{
    // ---- normalize_title() ----

    public function testNormalizeTitleTrimsSurroundingWhitespace(): void
    {
        $this->assertSame('Electricity bill', normalize_title('  Electricity bill  '));
    }

    public function testNormalizeTitleCollapsesInternalWhitespace(): void
    {
        $this->assertSame('Car tax renewal', normalize_title("Car   tax\trenewal"));
    }

    public function testNormalizeTitleOfBlankStringIsEmpty(): void
    {
        $this->assertSame('', normalize_title('    '));
    }

    // ---- is_valid_reminder_date() ----

    public function testValidDateIsAccepted(): void
    {
        $this->assertTrue(is_valid_reminder_date('2026-09-15'));
    }

    public function testWrongOrderIsRejected(): void
    {
        $this->assertFalse(is_valid_reminder_date('15-09-2026'));
    }

    public function testNonexistentCalendarDateIsRejected(): void
    {
        // June only has 30 days
        $this->assertFalse(is_valid_reminder_date('2026-06-31'));
    }

    public function testGarbageInputIsRejected(): void
    {
        $this->assertFalse(is_valid_reminder_date('not-a-date'));
    }

    // ---- filter_valid_reminder_items() ----

    public function testFilterKeepsItemsWithATitle(): void
    {
        $items = [
            ['title' => 'Bike tax'],
            ['title' => '   '],          // blank -> dropped
            ['title' => '  Car tax  '],  // kept, and normalized
        ];

        $result = filter_valid_reminder_items($items);

        $this->assertCount(2, $result);
        $this->assertSame('Bike tax', $result[0]['title']);
        $this->assertSame('Car tax', $result[1]['title']);
    }

    public function testFilterOfAllBlankTitlesReturnsEmptyArray(): void
    {
        $items = [['title' => ''], ['title' => '   ']];
        $this->assertSame([], filter_valid_reminder_items($items));
    }
}
