<?php

use PHPUnit\Framework\TestCase;
use Mfonte\ImdbScraper\Imdb;
use Mfonte\ImdbScraper\Entities\Dataset;
use Mfonte\ImdbScraper\Entities\SearchResult;
use Mfonte\ImdbScraper\Exceptions\BadMethodCall;

class FindTest extends TestCase
{
    /**
     * Test 1: Unfiltered search returns non-empty Dataset of SearchResult objects
     */
    public function testUnfilteredSearchReturnsNonEmptyDataset()
    {
        $imdb = Imdb::new();
        $results = $imdb->find('godfather');

        $this->assertInstanceOf(Dataset::class, $results);
        $this->assertGreaterThan(0, $results->count());

        foreach ($results as $result) {
            $this->assertInstanceOf(SearchResult::class, $result);
            $this->assertNotNull($result->id);
        }
    }

    /**
     * Test 2: type='movie' filter - all results satisfy isMovie()
     */
    public function testTypeMovieFilterReturnsOnlyMovies()
    {
        $imdb = Imdb::new();
        $results = $imdb->find('godfather', 'movie');

        $this->assertInstanceOf(Dataset::class, $results);
        $this->assertGreaterThan(0, $results->count());

        foreach ($results as $result) {
            $this->assertInstanceOf(SearchResult::class, $result);
            $this->assertTrue($result->isMovie(), "Result should be a movie: {$result->title}");
        }
    }

    /**
     * Test 3: type='tv' filter - all results satisfy isTvSeries()
     */
    public function testTypeTvFilterReturnsOnlyTvSeries()
    {
        $imdb = Imdb::new();
        $results = $imdb->find('breaking bad', 'tv');

        $this->assertInstanceOf(Dataset::class, $results);
        $this->assertGreaterThan(0, $results->count());

        foreach ($results as $result) {
            $this->assertInstanceOf(SearchResult::class, $result);
            $this->assertTrue($result->isTvSeries(), "Result should be a TV series: {$result->title}");
        }
    }

    /**
     * Test 4: year filter - all results have matching year
     */
    public function testYearFilterReturnsOnlyMatchingYear()
    {
        $imdb = Imdb::new();
        $results = $imdb->find('godfather', null, 1972);

        $this->assertInstanceOf(Dataset::class, $results);
        $this->assertGreaterThan(0, $results->count());

        foreach ($results as $result) {
            $this->assertInstanceOf(SearchResult::class, $result);
            $this->assertEquals(1972, $result->year, "Result year should be 1972: {$result->title}");
        }
    }

    /**
     * Test 5: Combined type+year filter
     */
    public function testCombinedTypeAndYearFilter()
    {
        $imdb = Imdb::new();
        $results = $imdb->find('inception', 'movie', 2010);

        $this->assertInstanceOf(Dataset::class, $results);
        $this->assertGreaterThan(0, $results->count());

        foreach ($results as $result) {
            $this->assertInstanceOf(SearchResult::class, $result);
            $this->assertTrue($result->isMovie(), "Result should be a movie: {$result->title}");
            $this->assertEquals(2010, $result->year, "Result year should be 2010: {$result->title}");
        }
    }

    /**
     * Test 6: Gibberish query returns empty Dataset, no exception
     */
    public function testGibberishQueryReturnsEmptyDataset()
    {
        $imdb = Imdb::new();
        $results = $imdb->find('xyzzy123nonsense456qwerty');

        $this->assertInstanceOf(Dataset::class, $results);
        $this->assertEquals(0, $results->count());
    }

    /**
     * Test 7: Invalid type throws BadMethodCall
     */
    public function testInvalidTypeThrowsBadMethodCall()
    {
        $this->expectException(BadMethodCall::class);

        $imdb = Imdb::new();
        $imdb->find('test', 'invalid');
    }
}
