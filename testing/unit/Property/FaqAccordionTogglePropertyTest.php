<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/helpers.php';

use PHPUnit\Framework\TestCase;

/**
 * Property-Based Test for FAQ accordion toggle involution.
 *
 * **Validates: Requirement 2.1**
 *
 * Property 3: Accordion toggle is an involution.
 * Test that for any FAQ item, toggling once flips the state, toggling twice restores the original state.
 */
class FaqAccordionTogglePropertyTest extends TestCase
{
    private const ITERATIONS = 1000;

    /**
     * Simulates toggle class logic.
     */
    private function toggleClass(array $classList, string $className): array
    {
        if (in_array($className, $classList, true)) {
            return array_values(array_diff($classList, [$className]));
        } else {
            $classList[] = $className;
            return $classList;
        }
    }

    /**
     * Simulates toggleFaq(button) operation.
     */
    private function simulateToggle(array $answerClasses, array $chevronClasses): array
    {
        if (in_array('faq-answer', $answerClasses, true)) {
            $answerClasses = $this->toggleClass($answerClasses, 'hidden');
        }

        if (in_array('faq-chevron', $chevronClasses, true)) {
            $chevronClasses = $this->toggleClass($chevronClasses, 'rotate-180');
        }

        return [
            'answer' => $answerClasses,
            'chevron' => $chevronClasses
        ];
    }

    /**
     * Property: Toggling once flips the state, and toggling twice restores the initial state (involution).
     * Validates: Requirement 2.1
     *
     * @test
     */
    public function toggleIsAnInvolution(): void
    {
        // Test with all possible boolean combinations for initial presence of state classes
        $booleanStates = [
            [true, true],
            [true, false],
            [false, true],
            [false, false]
        ];

        foreach ($booleanStates as [$hasHidden, $hasRotate]) {
            // Setup initial classes
            $initialAnswer = ['faq-answer'];
            if ($hasHidden) {
                $initialAnswer[] = 'hidden';
            }

            $initialChevron = ['faq-chevron'];
            if ($hasRotate) {
                $initialChevron[] = 'rotate-180';
            }

            // First Toggle
            $firstToggleResult = $this->simulateToggle($initialAnswer, $initialChevron);
            $after1Answer = $firstToggleResult['answer'];
            $after1Chevron = $firstToggleResult['chevron'];

            // Verify first toggle flips state
            $this->assertNotEquals(
                in_array('hidden', $initialAnswer, true),
                in_array('hidden', $after1Answer, true),
                "First toggle must flip the hidden class status of answer."
            );
            $this->assertNotEquals(
                in_array('rotate-180', $initialChevron, true),
                in_array('rotate-180', $after1Chevron, true),
                "First toggle must flip the rotate-180 class status of chevron."
            );

            // Second Toggle
            $secondToggleResult = $this->simulateToggle($after1Answer, $after1Chevron);
            $after2Answer = $secondToggleResult['answer'];
            $after2Chevron = $secondToggleResult['chevron'];

            // Verify second toggle restores original state
            $this->assertEquals(
                in_array('hidden', $initialAnswer, true),
                in_array('hidden', $after2Answer, true),
                "Second toggle must restore the original hidden class status of answer."
            );
            $this->assertEquals(
                in_array('rotate-180', $initialChevron, true),
                in_array('rotate-180', $after2Chevron, true),
                "Second toggle must restore the original rotate-180 class status of chevron."
            );
        }
    }

    /**
     * Property-based test with randomly generated class list noise.
     * Ensures other class names in the class list are unaffected,
     * and that the involution property still holds.
     * Validates: Requirement 2.1
     *
     * @test
     */
    public function toggleInvolutionWithClassListNoise(): void
    {
        $noiseClasses = ['w-full', 'px-5', 'py-4', 'flex', 'text-left', 'transition-transform', 'bg-white', 'text-sm'];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate initial classes with noise
            $initialAnswer = ['faq-answer'];
            $initialChevron = ['faq-chevron'];

            // Randomly add noise
            foreach ($noiseClasses as $noise) {
                if (mt_rand(0, 1) === 1) {
                    $initialAnswer[] = $noise;
                }
                if (mt_rand(0, 1) === 1) {
                    $initialChevron[] = $noise;
                }
            }

            // Randomly add the state classes
            $hasHidden = (mt_rand(0, 1) === 1);
            if ($hasHidden) {
                $initialAnswer[] = 'hidden';
            }
            $hasRotate = (mt_rand(0, 1) === 1);
            if ($hasRotate) {
                $initialChevron[] = 'rotate-180';
            }

            // Shuffle class lists to simulate random order
            shuffle($initialAnswer);
            shuffle($initialChevron);

            // First Toggle
            $firstToggleResult = $this->simulateToggle($initialAnswer, $initialChevron);
            $after1Answer = $firstToggleResult['answer'];
            $after1Chevron = $firstToggleResult['chevron'];

            // Verify first toggle flips state
            $this->assertNotEquals(
                in_array('hidden', $initialAnswer, true),
                in_array('hidden', $after1Answer, true)
            );
            $this->assertNotEquals(
                in_array('rotate-180', $initialChevron, true),
                in_array('rotate-180', $after1Chevron, true)
            );

            // Ensure other classes (noise) remain unchanged
            $expectedAnswerNoise = array_values(array_diff($initialAnswer, ['hidden']));
            $actualAnswerNoise = array_values(array_diff($after1Answer, ['hidden']));
            sort($expectedAnswerNoise);
            sort($actualAnswerNoise);
            $this->assertEquals($expectedAnswerNoise, $actualAnswerNoise, "Noise classes on answer must not be modified by toggle.");

            $expectedChevronNoise = array_values(array_diff($initialChevron, ['rotate-180']));
            $actualChevronNoise = array_values(array_diff($after1Chevron, ['rotate-180']));
            sort($expectedChevronNoise);
            sort($actualChevronNoise);
            $this->assertEquals($expectedChevronNoise, $actualChevronNoise, "Noise classes on chevron must not be modified by toggle.");

            // Second Toggle
            $secondToggleResult = $this->simulateToggle($after1Answer, $after1Chevron);
            $after2Answer = $secondToggleResult['answer'];
            $after2Chevron = $secondToggleResult['chevron'];

            // Verify second toggle restores original state classes exactly (including order-independent array content check)
            sort($initialAnswer);
            sort($after2Answer);
            sort($initialChevron);
            sort($after2Chevron);

            $this->assertEquals($initialAnswer, $after2Answer, "After two toggles, answer classes must match initial state exactly.");
            $this->assertEquals($initialChevron, $after2Chevron, "After two toggles, chevron classes must match initial state exactly.");
        }
    }
}
