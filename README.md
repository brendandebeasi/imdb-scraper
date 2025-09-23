# Mfonte IMDb Scraper

A **PHP library** for scraping movie, TV show, and person profile data from IMDb with ease. This package provides methods to retrieve detailed information about movies, TV shows, and people using best matches or strict year-based queries, **with support for localized searches, awards extraction, and comprehensive person profiles**.

> [!CAUTION]
> This package is intended for educational and personal use only. Users are responsible for ensuring their use complies with IMDb's Terms of Service and applicable laws. The author does not condone or encourage unauthorized scraping or other activities that violate legal agreements.
> Please, refer to the [IMDb Conditions of Use](https://www.imdb.com/conditions?utm_source) for more information.
> **It is your sole responsibility to use this package in compliance with IMDb's Terms of Service.**

## List of supported locales

- English (`en-US`) via `en`
- Italian (`it-IT`) via `it`
- Spanish (`es-ES`) via `es`
- French (`fr-FR`) via `fr`
- German (`de-DE`) via `de`
- Portuguese (`pt-BR`) via `pt`
- Indian (`hi-IN`) via `hi`

- - -

## Credits

This project is maintained by **Maurizio Fonte** and is a comprehensive PHP library for IMDb data extraction. The library has been extensively enhanced with new features including:
- Person profile extraction with biography, birth/death information, and known works
- Awards and festival nominations/wins extraction with structured data models
- Improved TV series episode data extraction
- Enhanced data type consistency using Dataset collections
- Comprehensive test coverage and debugging utilities

## Overview

Mfonte IMDb Scraper is a lightweight, object-oriented library to interact with IMDb. It provides functionalities to:

- Retrieve movie and TV show details by title and year, or IMDb ID, **with robust exception handling**, with support for **multiple locales**.
- Fetch person profiles including biography, birth information, professions, and known works.
- Extract awards and festival nominations/wins with detailed recipient information.
- Fetch data like plot, actors, genres, ratings, similar titles, and full cast/crew credits.
- Narrow results using "best match" algorithms or strict filters.

Key features include:

- **Localized searches** using the `locale` option.
- **Person profiles** with comprehensive biographical data.
- **Awards extraction** with structured festival and award data.
- Built-in **caching** for optimized performance.
- `v2` tag works from **PHP 8.1** onwards. `v1` tag Works from **PHP 7.3** to **PHP 8.0**.

- - -

## Table of Contents

- [Credits](#credits)
- [Installation](#installation)
- [Usage](#usage)
  - [Basic Example](#basic-example)
  - [The `Title` Object](#the-title-object)
  - [The `Person` Object](#the-person-object)
  - [Awards and Festivals](#awards-and-festivals)
  - [Options](#options)
  - [Methods](#methods)
    - [Best Match Overview](#best-match-overview)
    - [By Year Overview](#by-year-overview)
    - [Person Profiles](#person-profiles)
    - [Handling Exceptions](#handling-exceptions)
    - [The `id()` Method](#the-id-method)
    - [The `person()` Method](#the-person-method)
    - [The `search()` Method](#the-search-method)
- [Summary of Exceptions and Methods](#summary-of-exceptions-and-methods)
- [Advanced Features](#advanced-features)
  - [Caching](#caching)
  - [Locale](#locale)
  - [Proxy Configuration](#proxy-configuration)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Installation

Release tag `v1` is compatible with PHP versions `7.3`, `7.4` and `8.0` (monolog `^2.0`).

Install the package via Composer:

```bash
composer require mfonte/imdb-scraper "^1.0"
```

Release tag `v2` is compatible with PHP versions `8.1`, `8.2`, `8.3`, `8.4` (monolog `^3.0`).

Install the package via Composer:

```bash
composer require mfonte/imdb-scraper "^2.0"
```

- - -

## Usage

### Basic Example

```php
use Mfonte\ImdbScraper\Imdb;
use Mfonte\ImdbScraper\Exceptions\NoSearchResults;
use Mfonte\ImdbScraper\Exceptions\MultipleSearchResults;

// Create an IMDb scraper instance
$imdb = Imdb::new(['locale' => 'en']);

// Fetch movie details using best match
$movie = $imdb->movie('The Godfather');

// Output some details
echo $movie->title;  // The Godfather
echo $movie->year;   // 1972
echo $movie->plot;   // "The aging patriarch of an organized crime dynasty transfers control..."

// When using movie(), tvSeries(), movieByYear(), or tvSeriesByYear(), catch exceptions!
try {
    $movie = $imdb->movie('The Godfather');
} catch (NoSearchResults $e) {
    echo 'No results found!';
} catch (MultipleSearchResults $e) {
    echo 'Multiple results found!';
}

// you can also fetch a Title by its IMDB ID
$item = $imdb->id('tt0068646');
echo $item->title;  // The Godfather

// you can also only search for results
$results = $imdb->search('godfather');
```

All methods return a `Mfonte\ImdbScraper\Title` object with the following properties.

### The `Title` Object

The `Title` object represents detailed information about a movie or TV show fetched using the `ImdbScraper` library. It encapsulates all key attributes of a title, including metadata, cast, genres, and links to related content.

#### Properties of the `Title` Object

| **Property** | **Type** | **Description** |
| --- | --- | --- |
| **`id`** | `string` | The unique IMDb ID of the title (e.g., `"tt1234567"`). |
| **`isTvSeries`** | `bool` | Indicates whether the title is a TV series (`true`) or a movie (`false`). |
| **`link`** | `string` | The URL of the IMDb page for the title. |
| **`title`** | `string` | The official title of the movie or TV show. |
| **`originalTitle`** | `string` | The original title of the movie or TV show. |
| **`year`** | \`int | null\` |
| **`length`** | \`string | null\` |
| **`rating`** | \`float | null\` |
| **`ratingVotes`** | \`int | null\` |
| **`popularityScore`** | \`int | null\` |
| **`metaScore`** | \`int | null\` |
| **`genres`** | `array` | A list of genres associated with the title (e.g., `["Action", "Drama"]`). |
| **`posterUrl`** | \`string | null\` |
| **`trailerUrl`** | \`string | null\` |
| **`plot`** | \`string | null\` |
| **`actors`** | `Dataset` | A dataset containing the cast members of the title. (`Person` objects) |
| **`similars`** | `Dataset` | A dataset containing similar titles to the one fetched. (`Title` objects) |
| **`seasonRefs`** | `array` | A list of season numbers for a TV series (e.g., `[1, 2, 3, 4]`). |
| **`seasons`** | `Dataset` | A dataset containing detailed information about seasons for a TV series. (`Season` objects) |
| **`credits`** | `Dataset` | A dataset containing all credits associated with the title, including directors, writers, and producers. (`Credit` objects) |
| **`awards`** | `Dataset` | A dataset containing awards and festival information. Each `Festival` object contains the festival name and a Dataset of `Award` objects with category, year, outcome, and recipients. |
| **`metadata`** | `array` | Raw metadata associated with the title. |

### The `Person` Object

The `Person` object represents detailed information about an actor, director, or other crew member. It includes comprehensive biographical data and filmography information.

#### Properties of the `Person` Object

| **Property** | **Type** | **Description** |
| --- | --- | --- |
| **`id`** | `string` | The unique IMDb ID of the person (e.g., `"nm0000151"`). |
| **`name`** | `string` | The person's full name. |
| **`link`** | `string` | The URL of the IMDb page for the person. |
| **`image`** | `string\|null` | URL to the person's profile image. |
| **`bio`** | `string\|null` | Biographical summary of the person. |
| **`birthDate`** | `string\|null` | Date of birth (e.g., "June 1, 1937"). |
| **`birthPlace`** | `string\|null` | Place of birth (e.g., "Memphis, Tennessee, USA"). |
| **`deathDate`** | `string\|null` | Date of death if applicable. |
| **`knownFor`** | `array` | List of titles the person is known for, each containing title, year, and ID. |
| **`professions`** | `array` | List of professions (e.g., ["Actor", "Producer", "Director"]). |
| **`otherNames`** | `array` | Alternative names or nicknames. |
| **`character`** | `string\|null` | Character name (when Person is part of a cast list). |
| **`type`** | `string` | Type of person (e.g., "actor", "director"). |

### Awards and Festivals

The awards data is structured using two entity classes:

#### `Festival` Object
- **`id`** - Festival identifier (if available)
- **`name`** - Name of the festival or awards show
- **`awards`** - Dataset of Award objects

#### `Award` Object
- **`category`** - Award category name
- **`year`** - Year of the award
- **`outcome`** - Result (e.g., "Winner", "Nominee")
- **`recipients`** - Array of recipients with name and href

- - -

## Options

The scraper provides various configuration options during initialization:

| Option | Default | Description |
| --- | --- | --- |
| `cache` | `false` | Enables caching of results. |
| `locale` | `en` | Sets the locale for searches (e.g., `it` for Italian). |
| `seasons` | `false` | If `true`, fetches season data for TV shows. |
| `credits` | `false` | If `true`, fetches detailed credits information. |
| `awards` | `false` | If `true`, fetches awards and festival nominations/wins. |
| `guzzleLogFile` | `null` | File path for logging HTTP requests (useful for debugging). |
| `proxy` | `null` | Proxy configuration for HTTP requests (see Proxy Configuration section). |

### Example: Setting Options

```php
use Mfonte\ImdbScraper\Imdb;

// Enable caching and use Italian locale
$imdb = Imdb::new([
    'cache' => true,
    'locale' => 'it'
]);

// Fetch a localized movie
$movie = $imdb->movie('La ricerca della felicità');
echo $movie->title;  // La ricerca della felicità

// Fetch movie with awards information
$imdb = Imdb::new([
    'awards' => true
]);

$movie = $imdb->movie('The Godfather');
foreach ($movie->awards as $festival) {
    echo "Festival: " . $festival->name . "\n";
    foreach ($festival->awards as $award) {
        echo "  - " . $award->category . " (" . $award->year . " " . $award->outcome . ")\n";
        foreach ($award->recipients as $recipient) {
            echo "      " . $recipient['name'] . "\n";
        }
    }
}

// Fetch person profile
$person = $imdb->person('nm0000151');  // Morgan Freeman
echo $person->name . " was born on " . $person->birthDate . " in " . $person->birthPlace . "\n";
echo "Known for: ";
foreach ($person->knownFor as $i => $work) {
    if ($i > 0) echo ", ";
    echo $work['title'];
}
```

- - -

## Methods

### Best Match Overview

The `movie()` and `tvSeries()` methods find the best match for a given title. The library uses a **Levenshtein algorithm** to rank results and selects the closest match.

#### Example with `movie()`

```php
use Mfonte\ImdbScraper\Imdb;

// Fetch the best match for a movie title
$movie = Imdb::new()->movie('godfather');
echo $movie->id;       // tt0068646
echo $movie->title;    // The Godfather
echo $movie->year;     // 1972
```

- - -

### By Year Overview

The `movieByYear()` and `tvSeriesByYear()` methods perform a strict search for titles matching the specified year. If no exact match is found, the library checks for movies released one year before or after the given year.

#### Example with `movieByYear()`

```php
use Mfonte\ImdbScraper\Imdb;

// Fetch a movie by title and year
$movie = Imdb::new()->movieByYear('from dusk dawn', 1996);
echo $movie->id;       // tt0116367
echo $movie->title;    // From Dusk Till Dawn
echo $movie->year;     // 1996
```

- - -

## Handling Exceptions

The methods `movie()`, `tvSeries()`, `movieByYear()`, and `tvSeriesByYear()` in the `Imdb` class throw exceptions when specific conditions are not met during the search:

1. **`NoSearchResults` Exception**:
    - Thrown when no results are found for the provided query.
    - Example:

        ```php
        use Mfonte\ImdbScraper\Imdb;
        use Mfonte\ImdbScraper\Exceptions\NoSearchResults;

        $imdb = Imdb::new(['locale' => 'en']);

        try {
            $movie = $imdb->movie('nonexistent title');
        } catch (NoSearchResults $e) {
            echo 'No results found for the query.';
        }
        ```

2. **`MultipleSearchResults` Exception**:
    - Thrown when the query returns multiple results but the method cannot narrow them down to a single title.
    - Example:

        ```php
        use Mfonte\ImdbScraper\Imdb;
        use Mfonte\ImdbScraper\Exceptions\MultipleSearchResults;

        $imdb = Imdb::new(['locale' => 'en']);

        try {
            $movie = $imdb->movie('godfather');
        } catch (MultipleSearchResults $e) {
            echo 'Multiple results found for the query.';
        }
        ```

3. **`BadMethodCall` Exception**:
    - Thrown when invalid input is provided to the `id()` method or other API methods.
    - Example:

        ```php
        use Mfonte\ImdbScraper\Imdb;
        use Mfonte\ImdbScraper\Exceptions\BadMethodCall;

        $imdb = Imdb::new(['locale' => 'en']);

        try {
            $movie = $imdb->id('invalid_id');
        } catch (BadMethodCall $e) {
            echo 'Invalid IMDb ID provided.';
        }
        ```

- - -

## The `id()` Method

The `id()` method allows you to fetch detailed information about a movie or TV show using its unique IMDb ID.

```php
use Mfonte\ImdbScraper\Imdb;

$imdb = Imdb::new(['locale' => 'en']);

$movie = $imdb->id('tt0110912'); // Pulp Fiction IMDb ID

echo $movie->title;  // Pulp Fiction
echo $movie->year;   // 1994
```

**Key Features**:

- Accepts only valid IMDb IDs in the format `tt1234567`.
- Throws a `BadMethodCall` exception if the input is invalid.

- - -

## The `person()` Method

The `person()` method allows you to fetch detailed biographical information about a person (actor, director, etc.) using their unique IMDb ID.

```php
use Mfonte\ImdbScraper\Imdb;

$imdb = Imdb::new(['locale' => 'en']);

$person = $imdb->person('nm0000151'); // Morgan Freeman's IMDb ID

echo $person->name;        // Morgan Freeman
echo $person->birthDate;   // June 1, 1937
echo $person->birthPlace;  // Memphis, Tennessee, USA
echo substr($person->bio, 0, 100); // First 100 chars of biography

// Access known for titles
foreach ($person->knownFor as $title) {
    echo $title['title'] . " (" . $title['year'] . ")\n";
}
```

**Key Features**:

- Accepts only valid person IMDb IDs in the format `nm1234567` or `nm12345678`.
- Returns comprehensive biographical data including birth/death information.
- Includes filmography highlights in the `knownFor` field.
- Throws a `BadMethodCall` exception if the input is invalid.

- - -

### Person Profiles

Person profiles provide comprehensive information about actors, directors, and other film industry professionals:

```php
use Mfonte\ImdbScraper\Imdb;

$imdb = Imdb::new();

// Fetch a person's profile
$person = $imdb->person('nm0001401'); // Angelina Jolie

// Access biographical information
echo "Name: " . $person->name . "\n";
echo "Born: " . $person->birthDate . " in " . $person->birthPlace . "\n";
echo "Biography: " . substr($person->bio, 0, 200) . "...\n";

// Display professions
echo "Professions: " . implode(", ", $person->professions) . "\n";

// Show what they're known for
echo "Known For:\n";
foreach ($person->knownFor as $work) {
    echo "  - " . $work['title'];
    if ($work['year']) {
        echo " (" . $work['year'] . ")";
    }
    echo " [" . $work['id'] . "]\n";
}

// Check if they have a profile image
if ($person->image) {
    echo "Profile Image: " . $person->image . "\n";
}
```

- - -

## The `search()` Method

The `search()` method performs a general search query and returns a `Dataset` containing `SearchResult` objects for all matches.

```php
use Mfonte\ImdbScraper\Imdb;

$imdb = Imdb::new(['locale' => 'en']);

$results = $imdb->search('godfather');
foreach ($results as $result) {
    echo $result->title . " (" . $result->year . ")" . PHP_EOL;
}
```

**Key Features**:

- Returns a `Dataset` of `SearchResult` objects.
- Each `SearchResult` includes fields like `id`, `title`, `year`, `type`, and more.

- - -

## Summary of Exceptions and Methods

| **Method** | **Description** | **Exceptions Thrown** |
| --- | --- | --- |
| `movie($title)` | Fetches the best match for a movie title. | `NoSearchResults`, `MultipleSearchResults` |
| `tvSeries($title)` | Fetches the best match for a TV series title. | `NoSearchResults`, `MultipleSearchResults` |
| `movieByYear($title, $year)` | Fetches a movie by title and year. | `NoSearchResults`, `MultipleSearchResults` |
| `tvSeriesByYear($title, $year)` | Fetches a TV series by title and year. | `NoSearchResults`, `MultipleSearchResults` |
| `id($imdbId)` | Fetches a movie or TV show by IMDb ID (e.g., `tt1234567`). | `BadMethodCall` |
| `person($personId)` | Fetches a person's profile by IMDb ID (e.g., `nm0000151`). | `BadMethodCall` |
| `search($query)` | Performs a general search and returns a `Dataset` of `SearchResult` objects. | \-  |

With these robust exception-handling mechanisms and versatile methods, the `Imdb` class offers both flexibility and reliability for your IMDb scraping needs.

## Advanced Features

### Caching

Enable caching for faster repeated lookups. Cache works seamlessly and stores results locally.

```php
$imdb = Imdb::new(['cache' => true]);
$movie = $imdb->movie('Inception');
```

### Locale

Retrieve localized movie titles, plots, and other data by setting the `locale` option.

```php
$imdb = Imdb::new(['locale' => 'it']);
$movie = $imdb->movie('Pursuit Happyness');
echo $movie->title;  // La ricerca della felicità
```

### Proxy Configuration

The scraper supports proxy configuration for HTTP requests. This is useful when you need to route requests through a proxy server.

#### Simple HTTP Proxy

```php
$imdb = Imdb::new([
    'proxy' => 'http://proxy.example.com:8080'
]);
```

#### Proxy with Authentication

```php
$imdb = Imdb::new([
    'proxy' => 'http://username:password@proxy.example.com:8080'
]);
```

#### Different Proxies for HTTP/HTTPS

```php
$imdb = Imdb::new([
    'proxy' => [
        'http'  => 'http://proxy.example.com:8080',
        'https' => 'http://proxy.example.com:8080',
        'no' => ['.mit.edu', 'foo.com']  // Don't use proxy for these domains
    ]
]);
```

#### SOCKS Proxy

```php
$imdb = Imdb::new([
    'proxy' => 'socks5://proxy.example.com:1080'
]);
```

The proxy configuration is passed directly to Guzzle's HTTP client, so all Guzzle proxy formats are supported. See [Guzzle's proxy documentation](https://docs.guzzlephp.org/en/stable/request-options.html#proxy) for more details.

- - -

## Testing

The library includes comprehensive test scripts to verify functionality:

### Test Scripts

| **Script** | **Description** |
| --- | --- |
| `test-comprehensive.php` | Tests 5 movies, 5 TV series, and 5 person profiles |
| `test-awards.php` | Tests awards and festival data extraction |
| `test-actor-images.php` | Verifies person profile image extraction |
| `test-final-demo.php` | Comprehensive feature demonstration |
| `debug-episodes.php` | Debug tool for TV series episode extraction |
| `debug-awards.php` | Debug tool for awards data structure |

### Running Tests

```bash
# Run comprehensive test suite
php test-comprehensive.php

# Test awards functionality
php test-awards.php

# Test all features
php test-final-demo.php
```

### Example Test Output

```
=== Testing Awards Schema ===

Testing The Dark Knight (2008) (tt0468569)...
------------------------------------------------------------
✅ Awards loaded as Dataset
  Total festivals: 102
  First festival: Academy Awards, USA
    Awards in festival: 5
    First award:
      Category: Best Performance by an Actor in a Supporting Role
      Year: 2009
      Outcome: Winner
      Recipients: 1
  ✅ JSON serialization works
  ✅ JSON structure valid
```

### PHPUnit Tests

Run the PHPUnit test suite:

```bash
composer test
```

### Debugging

For debugging specific issues, use the debug scripts:

```php
// Debug episode extraction for a specific series
php debug-episodes.php tt0903747  # Breaking Bad

// Debug awards structure
php debug-awards.php
```

- - -

## Contributing

Contributions are welcome! Feel free to open an issue or submit a pull request.

- - -

## License

This project is licensed under the MIT License. See the LICENSE file for details.
