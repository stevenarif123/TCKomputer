# Implementation Plan

- [x] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Homepage Top-Section Over-Height and Loose Spacing
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior and will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the homepage compaction bug exists
  - **Scoped PBT Approach**: For deterministic UI bug reproduction, scope generated cases to representative mobile and desktop viewports with existing homepage data
  - Define bug condition predicate from specification as `isBugCondition(layoutMetrics)`:
    - top section cumulative height is too large before first product card becomes visible
    - inter-component vertical gaps in top section are too wide
    - featured category block renders too many initial category items
    - category tile spacing is not compact (non-zero/overly large gap)
  - Implement property-based exploration test that, for generated viewport sizes and homepage states, asserts expected compact behavior (from Expected Behavior) and records failing examples on UNFIXED code
  - Run on UNFIXED code and document counterexamples (example format: viewport, measured top height, measured gaps, first product visibility position)
  - **EXPECTED OUTCOME**: Test FAILS (this is correct and confirms the bug)
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 2.4_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Homepage Interaction and Product Rendering Stability
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for non-bug condition scenarios (`¬isBugCondition`) and record baseline outputs:
    - banner/promo/category click targets remain functional
    - category labels remain readable and selectable
    - product list elements (name, price, actions) remain rendered correctly
    - responsive rendering still works across mobile and desktop breakpoints
  - Write property-based tests over non-buggy generated inputs (viewport sizes, interaction targets, category/product fixtures) that encode observed baseline behavior from preservation requirements
  - Run preservation tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (confirms baseline behavior to preserve)
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [ ] 3. Fix homepage layout compaction behavior

  - [x] 3.1 Implement the fix
    - Reduce cumulative vertical footprint of the top homepage components so products appear sooner
    - Tighten vertical spacing between top components while preserving readability
    - Limit initial rendered featured categories in the top section to prevent category dominance
    - Apply compact category tile presentation with no inter-tile gap while maintaining clear visual hierarchy
    - Update responsive CSS/layout rules for both mobile and desktop breakpoints
    - _Bug_Condition: `isBugCondition(layoutMetrics)` where top area is over-height, gaps are too wide, category count is excessive, or category tiles are not compact_
    - _Expected_Behavior: `expectedBehavior(layoutMetrics)` where top area is compact, spacing is tighter, initial categories are limited, and category tiles are gapless but clear_
    - _Preservation: Existing click/navigation behavior, category readability/selectability, product card rendering, and responsiveness remain unchanged_
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4_

  - [x] 3.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Homepage Compaction Satisfies Bug Condition Assertions
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - Execute the bug condition exploration property test created in step 1 after the implementation changes
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

  - [-] 3.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Homepage Existing Behavior Remains Stable
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Execute preservation property tests from step 2 after fix implementation
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [ ] 4. Checkpoint - Ensure all tests pass
  - Run the relevant property tests and any targeted homepage regression checks in single-run mode (non-watch)
  - Confirm Property 1 passes after fix and Property 2 remains passing
  - Ensure no new regressions are introduced in homepage rendering or interactions
  - Ask the user for clarification if any assertion is ambiguous during validation
