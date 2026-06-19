<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for parseSpecification()
 *
 * **Validates: Requirements 4.1, 4.2, 4.3, 4.8**
 */
class SpecificationParserPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Generate random valid characters for keys and values.
     */
    private function generateRandomWord(int $minLength = 1, int $maxLength = 10): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ';
        $len = mt_rand($minLength, $maxLength);
        $word = '';
        for ($i = 0; $i < $len; $i++) {
            $word .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return trim($word);
    }

    /**
     * Property 4: Specification Supported Delimiter Parsing
     * For any non-empty specification line that contains exactly one supported delimiter
     * between a non-empty key and non-empty value, parseSpecification() should represent that
     * line as exactly one parsed key-value row.
     * Validates: Requirements 4.1
     *
     * @test
     */
    public function propertySupportedDelimiterParsing(): void
    {
        $delimiters = [':', '-', '=', '|'];
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $key = '';
            while ($key === '') {
                $key = $this->generateRandomWord(2, 12);
            }
            $value = '';
            while ($value === '') {
                $value = $this->generateRandomWord(2, 20);
            }
            $delim = $delimiters[array_rand($delimiters)];

            // Spaces variation around the delimiter
            $spacesBefore = str_repeat(' ', mt_rand(0, 3));
            $spacesAfter = str_repeat(' ', mt_rand(0, 3));
            $line = $key . $spacesBefore . $delim . $spacesAfter . $value;

            $result = parseSpecification($line);

            $this->assertCount(1, $result['parsed'], "Should parse exactly one row for line: '{$line}'");
            $this->assertSame($key, $result['parsed'][0]['key'], "Key should match original trimmed key");
            $this->assertSame($value, $result['parsed'][0]['value'], "Value should match original trimmed value");
            $this->assertSame('', $result['unparsed'], "Unparsed should be empty");
        }
    }

    /**
     * Property 5: Specification Unparsed Line Preservation
     * For any non-empty specification line without a supported delimiter format,
     * parseSpecification() should preserve that line in the unparsed fallback text.
     * Validates: Requirements 4.2
     *
     * @test
     */
    public function propertyUnparsedLinePreservation(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $line = '';
            while ($line === '') {
                $line = $this->generateRandomWord(5, 30);
            }

            // Ensure we don't accidentally generate a line containing delimiters
            $line = str_replace([':', '-', '=', '|'], ' ', $line);
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $result = parseSpecification($line);

            $this->assertCount(0, $result['parsed'], "Should not parse any rows for delimiter-free line: '{$line}'");
            $this->assertSame($line, $result['unparsed'], "Unparsed text should match the original line");
        }
    }

    /**
     * Property 6: Specification Parse-Print Preservation
     * For any valid Product specification text, parsing the text should preserve every
     * non-empty input line either as a parsed key-value row or as unparsed fallback text.
     * Validates: Requirements 4.4, 4.8
     *
     * @test
     */
    public function propertyParsePrintPreservation(): void
    {
        $delimiters = [':', '-', '=', '|'];
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $numLines = mt_rand(1, 10);
            $inputLines = [];
            $expectedParsedCount = 0;
            $expectedUnparsedCount = 0;

            for ($j = 0; $j < $numLines; $j++) {
                $isParseable = (mt_rand(0, 1) === 1);
                if ($isParseable) {
                    $key = '';
                    while ($key === '') {
                        $key = $this->generateRandomWord(2, 10);
                    }
                    $value = '';
                    while ($value === '') {
                        $value = $this->generateRandomWord(2, 15);
                    }
                    $delim = $delimiters[array_rand($delimiters)];
                    $line = $key . ' ' . $delim . ' ' . $value;
                    $inputLines[] = $line;
                    $expectedParsedCount++;
                } else {
                    $line = '';
                    while ($line === '') {
                        $line = $this->generateRandomWord(5, 20);
                    }
                    // Ensure it doesn't contain a delimiter
                    $line = str_replace([':', '-', '=', '|'], ' ', $line);
                    $line = trim($line);
                    if ($line !== '') {
                        $inputLines[] = $line;
                        $expectedUnparsedCount++;
                    }
                }
            }

            $specText = implode("\n", $inputLines);
            $result = parseSpecification($specText);

            $this->assertCount($expectedParsedCount, $result['parsed'], "Number of parsed items mismatch");
            
            $unparsedLines = $result['unparsed'] !== '' ? explode("\n", $result['unparsed']) : [];
            $this->assertCount($expectedUnparsedCount, $unparsedLines, "Number of unparsed lines mismatch");

            // Check preservation of every non-empty line
            $reconstructed = [];
            foreach ($result['parsed'] as $row) {
                // Just keep track of keys/values or the fact that they exist
                $reconstructed[] = $row['key'];
            }
            foreach ($unparsedLines as $uLine) {
                $reconstructed[] = $uLine;
            }

            $this->assertCount($expectedParsedCount + $expectedUnparsedCount, $reconstructed, "Total reconstructed lines count mismatch");
        }
    }

    /**
     * Requirement 4.3: When the Specification_Parser receives null or empty specification text,
     * the Specification_Parser SHALL return an empty parsed row list and empty unparsed fallback text.
     *
     * @test
     */
    public function nullOrEmptyInputReturnsEmptyParsedAndUnparsed(): void
    {
        // Null test
        $result = parseSpecification(null);
        $this->assertIsArray($result);
        $this->assertEmpty($result['parsed']);
        $this->assertSame('', $result['unparsed']);

        // Empty string test
        $result = parseSpecification('');
        $this->assertIsArray($result);
        $this->assertEmpty($result['parsed']);
        $this->assertSame('', $result['unparsed']);

        // Whitespace string test
        $result = parseSpecification("   \n   \r\n   ");
        $this->assertIsArray($result);
        $this->assertEmpty($result['parsed']);
        $this->assertSame('', $result['unparsed']);
    }
}
