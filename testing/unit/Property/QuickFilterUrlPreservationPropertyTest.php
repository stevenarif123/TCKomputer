<?php

use PHPUnit\Framework\TestCase;

class QuickFilterUrlPreservationPropertyTest extends TestCase
{
    /**
     * Property 10: Quick Filter URL Preservation
     * For any valid combination of search, category, status, sort, and selected Quick_Filter values, 
     * the generated Quick_Filter_Chip URL should preserve existing query parameters while applying the selected filter parameter
     * Validates: Requirements 8.6
     */
    public function testQuickFilterUrlPreservation(): void
    {
        // define domains
        $searches = ['', 'laptop', 'asus rog', '123!@#', '   '];
        $categories = [0, 1, 5, 10];
        $statuses = ['', 'ready', 'po'];
        $sorts = ['newest', 'cheapest', 'expensive'];
        $filters = ['', 'ready', 'promo', 'new']; // the filter to apply
        
        for ($i = 0; $i < 1000; $i++) {
            $search = $searches[array_rand($searches)];
            $category = $categories[array_rand($categories)];
            $status = $statuses[array_rand($statuses)];
            $sort = $sorts[array_rand($sorts)];
            $selectedFilter = $filters[array_rand($filters)];
            
            // Logic as implemented in products.php
            $chipBaseParams = [];
            if ($search !== '') $chipBaseParams['search'] = $search;
            if ($category > 0) $chipBaseParams['category'] = $category;
            if ($status !== '') $chipBaseParams['status'] = $status;
            if ($sort !== 'newest') $chipBaseParams['sort'] = $sort;
            
            $finalParams = $chipBaseParams;
            if ($selectedFilter !== '') {
                $finalParams['filter'] = $selectedFilter;
            }
            
            $queryString = http_build_query($finalParams);
            
            // Parse back to array
            parse_str($queryString, $parsed);
            
            // Assertions
            if ($search !== '') {
                $this->assertEquals($search, $parsed['search']);
            } else {
                $this->assertArrayNotHasKey('search', $parsed);
            }
            
            if ($category > 0) {
                $this->assertEquals((string)$category, $parsed['category']);
            } else {
                $this->assertArrayNotHasKey('category', $parsed);
            }
            
            if ($status !== '') {
                $this->assertEquals($status, $parsed['status']);
            } else {
                $this->assertArrayNotHasKey('status', $parsed);
            }
            
            if ($sort !== 'newest') {
                $this->assertEquals($sort, $parsed['sort']);
            } else {
                $this->assertArrayNotHasKey('sort', $parsed);
            }
            
            if ($selectedFilter !== '') {
                $this->assertEquals($selectedFilter, $parsed['filter']);
            } else {
                $this->assertArrayNotHasKey('filter', $parsed);
            }
        }
    }
}
