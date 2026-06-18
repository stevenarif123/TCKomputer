<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for FAQ search filter.
 *
 * **Validates: Requirements 3.2, 3.4**
 *
 * Property 4: Search filter returns case-insensitive matches and clear restores all
 */
class FaqSearchFilterPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Simulates client-side search filter logic in JS.
     */
    public function simulateSearchFilter(array $faqs, string $searchTerm): array
    {
        $searchVal = trim(mb_strtolower($searchTerm));
        if ($searchVal === '') {
            return $faqs;
        }

        $filtered = [];
        foreach ($faqs as $faq) {
            $question = $faq['question'] ?? '';
            $answer = $faq['answer'] ?? '';
            $questionText = mb_strtolower($question);
            $answerText = mb_strtolower($answer);

            if (mb_strpos($questionText, $searchVal) !== false || mb_strpos($answerText, $searchVal) !== false) {
                $filtered[] = $faq;
            }
        }
        return $filtered;
    }

    /**
     * Generate a random string of alphanumeric characters of a given length.
     */
    private function randomString(int $length): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $str;
    }

    /**
     * Property: Search filter returns case-insensitive matches and clear restores all.
     * Validates: Requirements 3.2, 3.4
     *
     * @test
     */
    public function testSearchFilterProperties(): void
    {
        $words = ['apple', 'banana', 'cherry', 'date', 'elderberry', 'fig', 'grape', 'honeydew', 'kiwi', 'lemon'];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate a random number of FAQs (1 to 20)
            $faqCount = mt_rand(1, 20);
            $faqs = [];
            for ($j = 0; $j < $faqCount; $j++) {
                // Generate random question and answer by combining some words or random characters
                $qWords = [];
                $qWordCount = mt_rand(1, 4);
                for ($w = 0; $w < $qWordCount; $w++) {
                    $qWords[] = $words[array_rand($words)];
                }
                $question = implode(' ', $qWords);

                $aWords = [];
                $aWordCount = mt_rand(1, 4);
                for ($w = 0; $w < $aWordCount; $w++) {
                    $aWords[] = $words[array_rand($words)];
                }
                $answer = implode(' ', $aWords);

                $faqs[] = [
                    'id' => $j,
                    'question' => $question,
                    'answer' => $answer
                ];
            }

            // Decide on a search term strategy
            $strategy = mt_rand(0, 4);
            switch ($strategy) {
                case 0:
                    // Empty search term (Requirement 3.4)
                    $searchTerm = '';
                    break;
                case 1:
                    // Whitespace only search term (Requirement 3.4)
                    $searchTerm = str_repeat(' ', mt_rand(1, 5));
                    break;
                case 2:
                    // Guaranteed match from one of the FAQs' questions or answers, possibly with mixed casing and spaces
                    $randomFaq = $faqs[array_rand($faqs)];
                    $targetField = mt_rand(0, 1) === 0 ? $randomFaq['question'] : $randomFaq['answer'];
                    $targetWords = explode(' ', $targetField);
                    $searchTerm = $targetWords[array_rand($targetWords)];
                    
                    // Add random spaces and change casing
                    $searchTerm = str_repeat(' ', mt_rand(0, 2)) . $this->randomizeCasing($searchTerm) . str_repeat(' ', mt_rand(0, 2));
                    break;
                case 3:
                    // Random alphabetic search term (might not match)
                    $searchTerm = $this->randomString(mt_rand(1, 5));
                    break;
                case 4:
                    // Substring of a word with random casing
                    $word = $words[array_rand($words)];
                    $subLength = mt_rand(1, strlen($word));
                    $searchTerm = substr($word, 0, $subLength);
                    $searchTerm = $this->randomizeCasing($searchTerm);
                    break;
            }

            // Simulate the filter
            $filtered = $this->simulateSearchFilter($faqs, $searchTerm);

            // Assertions based on search terms
            $searchValClean = trim(mb_strtolower($searchTerm));

            if ($searchValClean === '') {
                // Requirement 3.4: if cleared, restores all
                $this->assertCount(count($faqs), $filtered, "Cleared/empty search term must restore all FAQs.");
                $this->assertSame($faqs, $filtered, "Cleared/empty search term must return exactly the original FAQs list.");
            } else {
                // Requirement 3.2: check each returned FAQ matches, and check that no matching FAQ was left out
                foreach ($filtered as $faq) {
                    $qMatch = mb_strpos(mb_strtolower($faq['question']), $searchValClean) !== false;
                    $aMatch = mb_strpos(mb_strtolower($faq['answer']), $searchValClean) !== false;
                    $this->assertTrue(
                        $qMatch || $aMatch,
                        sprintf(
                            "Returned FAQ (Q: '%s', A: '%s') does not contain search term '%s'",
                            $faq['question'],
                            $faq['answer'],
                            $searchValClean
                        )
                    );
                }

                // Check that all FAQs containing the search term are actually returned
                foreach ($faqs as $faq) {
                    $qMatch = mb_strpos(mb_strtolower($faq['question']), $searchValClean) !== false;
                    $aMatch = mb_strpos(mb_strtolower($faq['answer']), $searchValClean) !== false;
                    if ($qMatch || $aMatch) {
                        $this->assertContains(
                            $faq,
                            $filtered,
                            sprintf(
                                "FAQ (Q: '%s', A: '%s') contains search term '%s' but was not returned.",
                                $faq['question'],
                                $faq['answer'],
                                $searchValClean
                            )
                        );
                    } else {
                        $this->assertNotContains(
                            $faq,
                            $filtered,
                            sprintf(
                                "FAQ (Q: '%s', A: '%s') does not contain search term '%s' but was returned.",
                                $faq['question'],
                                $faq['answer'],
                                $searchValClean
                            )
                        );
                    }
                }
            }
        }
    }

    /**
     * Randomize the casing of a string.
     */
    private function randomizeCasing(string $str): string
    {
        $result = '';
        $len = mb_strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($str, $i, 1);
            $result .= mt_rand(0, 1) === 0 ? mb_strtolower($char) : mb_strtoupper($char);
        }
        return $result;
    }
}
