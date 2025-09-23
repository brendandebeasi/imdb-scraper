#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Mfonte\ImdbScraper\Imdb;

/**
 * IMDB Scraper Test Suite
 * Comprehensive testing of all major features
 */

echo "=====================================\n";
echo "     IMDB SCRAPER TEST SUITE\n";
echo "=====================================\n\n";

$totalTests = 0;
$passedTests = 0;
$results = [];

// Color output helpers
function success($msg) { return "\033[32m$msg\033[0m"; }
function error($msg) { return "\033[31m$msg\033[0m"; }
function warning($msg) { return "\033[33m$msg\033[0m"; }
function info($msg) { return "\033[36m$msg\033[0m"; }

// Test helper
function runTest($name, $test) {
    global $totalTests, $passedTests, $results;
    $totalTests++;
    echo "  $name... ";
    
    try {
        $result = $test();
        if ($result) {
            echo success("✅ PASS") . "\n";
            $passedTests++;
            $results[$name] = 'pass';
            return true;
        } else {
            echo error("❌ FAIL") . "\n";
            $results[$name] = 'fail';
            return false;
        }
    } catch (Exception $e) {
        echo error("❌ ERROR: " . $e->getMessage()) . "\n";
        $results[$name] = 'error';
        return false;
    }
}

// ============================================
// 1. MOVIE TESTS
// ============================================
echo info("1. MOVIE TESTS") . "\n";
echo str_repeat('-', 40) . "\n";

runTest("The Shawshank Redemption", function() {
    $imdb = Imdb::new();
    $movie = $imdb->id('tt0111161');
    return $movie->title === 'The Shawshank Redemption' 
        && $movie->year == 1994 
        && $movie->rating >= 9.0
        && !empty($movie->plot)
        && count($movie->actors) > 0
        && count($movie->genres) > 0
        && !empty($movie->posterUrl);
});

runTest("The Dark Knight", function() {
    $imdb = Imdb::new();
    $movie = $imdb->id('tt0468569');
    return $movie->title === 'The Dark Knight' 
        && $movie->year == 2008
        && !empty($movie->rating)
        && count($movie->actors) > 0
        && !empty($movie->length); // Changed from runtime to length
});

runTest("Interstellar", function() {
    $imdb = Imdb::new();
    $movie = $imdb->id('tt0816692');
    return $movie->title === 'Interstellar'
        && $movie->year == 2014
        && !empty($movie->plot)
        && count($movie->genres) > 0
        && !empty($movie->posterUrl);
});

// ============================================
// 2. AWARDS TESTS
// ============================================
echo "\n" . info("2. AWARDS TESTS") . "\n";
echo str_repeat('-', 40) . "\n";

runTest("Awards - The Dark Knight", function() {
    $imdb = Imdb::new(['awards' => true]);
    $movie = $imdb->id('tt0468569');
    
    if (!isset($movie->awards) || $movie->awards === null) {
        echo "\n    " . warning("Awards not available") . "\n";
        return false;
    }
    
    // Awards is a Dataset of festivals, each containing awards
    $hasAwards = false;
    foreach ($movie->awards as $festival) {
        if (isset($festival->awards) && count($festival->awards) > 0) {
            $hasAwards = true;
            break;
        }
    }
    
    return $hasAwards;
});

runTest("Awards - The Lord of the Rings", function() {
    $imdb = Imdb::new(['awards' => true]);
    $movie = $imdb->id('tt0167260'); // Return of the King
    
    if (!isset($movie->awards) || $movie->awards === null) {
        return false;
    }
    
    // Check for Academy Awards/Oscars
    $hasOscars = false;
    foreach ($movie->awards as $festival) {
        if (isset($festival->name) && 
            (stripos($festival->name, 'Academy') !== false || 
             stripos($festival->name, 'Oscar') !== false)) {
            $hasOscars = true;
            break;
        }
    }
    
    return $hasOscars;
});

// ============================================
// 3. TV SERIES TESTS
// ============================================
echo "\n" . info("3. TV SERIES TESTS") . "\n";
echo str_repeat('-', 40) . "\n";

runTest("Breaking Bad - Basic", function() {
    $imdb = Imdb::new(['seasons' => false]);
    $series = $imdb->id('tt0903747');
    return $series->title === 'Breaking Bad'
        && $series->isTvSeries === true
        && count($series->seasonRefs) >= 5;
});

runTest("Breaking Bad - With Seasons", function() {
    $imdb = Imdb::new(['seasons' => true]);
    $series = $imdb->id('tt0903747');
    
    if (count($series->seasons) != 5) {
        return false;
    }
    
    // Check first season has 7 episodes
    $season1 = $series->seasons->first();
    return count($season1->episodes) == 7;
});

runTest("Game of Thrones - Basic", function() {
    $imdb = Imdb::new(['seasons' => false]);
    $series = $imdb->id('tt0944947');
    return $series->title === 'Game of Thrones'
        && $series->isTvSeries === true
        && count($series->seasonRefs) >= 8;
});

runTest("Friends - Season Detection", function() {
    $imdb = Imdb::new(['seasons' => false]);
    $series = $imdb->id('tt0108778');
    return $series->title === 'Friends'
        && count($series->seasonRefs) == 10;
});

// ============================================
// 4. PERSON PROFILE TESTS
// ============================================
echo "\n" . info("4. PERSON PROFILE TESTS") . "\n";
echo str_repeat('-', 40) . "\n";

runTest("Morgan Freeman", function() {
    $imdb = Imdb::new();
    $person = $imdb->person('nm0000151');
    return $person->name === 'Morgan Freeman'
        && $person->id === 'nm0000151'
        && !empty($person->bio)
        && count($person->knownFor) >= 3
        && !empty($person->image);
});

runTest("Johnny Depp", function() {
    $imdb = Imdb::new();
    $person = $imdb->person('nm0000136');
    return $person->name === 'Johnny Depp'
        && !empty($person->birthDate)
        && !empty($person->birthPlace)
        && count($person->knownFor) > 0;
});

runTest("Brad Pitt", function() {
    $imdb = Imdb::new();
    $person = $imdb->person('nm0000093');
    return $person->name === 'Brad Pitt'
        && !empty($person->bio)
        && count($person->knownFor) >= 3;
});

runTest("Angelina Jolie", function() {
    $imdb = Imdb::new();
    $person = $imdb->person('nm0001401');
    return $person->name === 'Angelina Jolie'
        && !empty($person->image)
        && count($person->knownFor) > 0;
});

// ============================================
// 5. SEARCH FUNCTIONALITY TESTS
// ============================================
echo "\n" . info("5. SEARCH FUNCTIONALITY TESTS") . "\n";
echo str_repeat('-', 40) . "\n";

runTest("Search - The Matrix", function() {
    $imdb = Imdb::new();
    $results = $imdb->search('The Matrix');
    return count($results) > 0;
});

runTest("Search - Breaking Bad", function() {
    $imdb = Imdb::new();
    $results = $imdb->search('Breaking Bad');
    
    // Check if we got the right result
    foreach ($results as $result) {
        if ($result->id === 'tt0903747') {
            return true;
        }
    }
    return false;
});

runTest("Search - Morgan Freeman", function() {
    $imdb = Imdb::new();
    $results = $imdb->search('Morgan Freeman');
    return count($results) > 0;
});

// ============================================
// 6. ERROR HANDLING TESTS
// ============================================
echo "\n" . info("6. ERROR HANDLING TESTS") . "\n";
echo str_repeat('-', 40) . "\n";

runTest("Invalid Movie ID", function() {
    $imdb = Imdb::new();
    try {
        $movie = $imdb->id('invalid123');
        return false; // Should have thrown exception
    } catch (Exception $e) {
        return true; // Correctly threw exception
    }
});

runTest("Invalid Person ID", function() {
    $imdb = Imdb::new();
    try {
        $person = $imdb->person('invalid456');
        return false; // Should have thrown exception
    } catch (Exception $e) {
        return true; // Correctly threw exception
    }
});

runTest("Empty Search Query", function() {
    $imdb = Imdb::new();
    try {
        $results = $imdb->search('');
        // Some implementations might return empty array instead of throwing
        return is_array($results) || $results instanceof Traversable;
    } catch (Exception $e) {
        return true; // Also acceptable to throw exception
    }
});

// ============================================
// 7. EDGE CASES
// ============================================
echo "\n" . info("7. EDGE CASES") . "\n";
echo str_repeat('-', 40) . "\n";

runTest("Movie with minimal data", function() {
    // Test with an obscure/old movie that might have less data
    $imdb = Imdb::new();
    $movie = $imdb->id('tt0000001'); // First movie in IMDB
    return !empty($movie->title) && !empty($movie->year);
});

runTest("Person with minimal data", function() {
    // Test with a less famous person
    $imdb = Imdb::new();
    $person = $imdb->person('nm0000001'); // Fred Astaire
    return !empty($person->name) && $person->id === 'nm0000001';
});

// ============================================
// 8. DATA INTEGRITY TESTS
// ============================================
echo "\n" . info("8. DATA INTEGRITY TESTS") . "\n";
echo str_repeat('-', 40) . "\n";

runTest("Plot truncation handling", function() {
    $imdb = Imdb::new();
    $movie = $imdb->id('tt0111161'); // Shawshank Redemption
    // Check that plot doesn't end with "..." (was truncated properly)
    return !empty($movie->plot) && substr($movie->plot, -3) !== '...';
});

runTest("Actor image URLs", function() {
    $imdb = Imdb::new();
    $movie = $imdb->id('tt0468569'); // The Dark Knight
    
    $hasValidImages = true;
    foreach ($movie->actors as $actor) {
        if (isset($actor->image) && !empty($actor->image)) {
            // Check if it's a valid URL
            if (!filter_var($actor->image, FILTER_VALIDATE_URL)) {
                $hasValidImages = false;
                break;
            }
        }
    }
    return $hasValidImages;
});

// ============================================
// SUMMARY
// ============================================
echo "\n" . str_repeat('=', 40) . "\n";
echo info("           TEST SUMMARY") . "\n";
echo str_repeat('=', 40) . "\n\n";

// Group results by category
$categories = [
    'Movies' => ['The Shawshank Redemption', 'The Dark Knight', 'Interstellar'],
    'Awards' => ['Awards - The Dark Knight', 'Awards - The Lord of the Rings'],
    'TV Series' => ['Breaking Bad - Basic', 'Breaking Bad - With Seasons', 'Game of Thrones - Basic', 'Friends - Season Detection'],
    'Person Profiles' => ['Morgan Freeman', 'Johnny Depp', 'Brad Pitt', 'Angelina Jolie'],
    'Search' => ['Search - The Matrix', 'Search - Breaking Bad', 'Search - Morgan Freeman'],
    'Error Handling' => ['Invalid Movie ID', 'Invalid Person ID', 'Empty Search Query'],
    'Edge Cases' => ['Movie with minimal data', 'Person with minimal data'],
    'Data Integrity' => ['Plot truncation handling', 'Actor image URLs']
];

foreach ($categories as $category => $tests) {
    $catPassed = 0;
    $catTotal = count($tests);
    foreach ($tests as $test) {
        if (isset($results[$test]) && $results[$test] === 'pass') {
            $catPassed++;
        }
    }
    $status = $catPassed == $catTotal ? success("✅") : ($catPassed > 0 ? warning("⚠️") : error("❌"));
    echo "$status $category: $catPassed/$catTotal passed\n";
}

echo str_repeat('-', 40) . "\n";
echo "TOTAL: $passedTests/$totalTests tests passed\n";

$percentage = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
echo "Success Rate: {$percentage}%\n\n";

if ($percentage >= 90) {
    echo success("✅ Excellent! All major features working correctly.") . "\n";
} elseif ($percentage >= 75) {
    echo warning("⚠️  Good. Most features working, some minor issues.") . "\n";
} elseif ($percentage >= 60) {
    echo warning("⚠️  Fair. Core features working but needs improvement.") . "\n";
} else {
    echo error("❌ Poor. Significant issues detected.") . "\n";
}

// Save results for tracking
$resultData = [
    'date' => date('Y-m-d H:i:s'),
    'total' => $totalTests,
    'passed' => $passedTests,
    'percentage' => $percentage,
    'results' => $results
];

file_put_contents('test-results.json', json_encode($resultData, JSON_PRETTY_PRINT));
echo "\nDetailed results saved to test-results.json\n";

// Exit with appropriate code
exit($percentage >= 75 ? 0 : 1);