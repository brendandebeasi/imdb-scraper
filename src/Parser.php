<?php
namespace Mfonte\ImdbScraper;

use voku\helper\HtmlDomParser;
use voku\helper\SimpleHtmlDom;
use Mfonte\ImdbScraper\Entities\Title;
use Mfonte\ImdbScraper\Entities\Person;

/**
* Class Parser
*
* @package mfonte/imdb-scraper
* @author Maurizio Fonte
*/
class Parser
{
    private const SENTENCE_PUNCTUATION = ['?', '!', '...', '.'];
    /**
     * @var string|null The IMDB identifier
     */
    private $id = null;

    /**
     * @var array $options
     */
    private $options = [];

    /**
     * @var array $properties
     */
    private $properties = [
        'metadata' => [],
        'isTvSeries' => false,
        'title' => '',
        'originalTitle' => '',
        'year' => null,
        'length' => '',
        'rating' => null,
        'ratingVotes' => null,
        'popularityScore' => null,
        'metaScore' => null,
        'genres' => [],
        'posterUrl' => null,
        'trailerUrl' => null,
        'plot' => '',
        'actors' => [],
        'similars' => [],
        'seasonRefs' => [],
        'seasons' => [],
        'credits' => [],
        'awards' => null,
    ];

    /**
     * Expects an IMDB identifier in the form of 'tt1234567'
     *
     * @param string|null $id - IMDB identifier in the form of 'tt1234567'
     *
     * @return Parser
     */
    public static function parse(string $id, array $options = []) : Parser
    {
        self::validateId($id);

        return (new self($id, $options))->runParser();
    }

    /**
     * Parses a person profile from IMDB
     *
     * @param string $id - IMDB identifier in the form of 'nm1234567'
     * @param array $options
     *
     * @return Parser
     */
    public static function parsePerson(string $id, array $options = []) : Parser
    {
        self::validatePersonId($id);

        return (new self($id, $options))->runPersonParser();
    }

    /**
     * Constructor. Expects an IMDB identifier in the form of 'tt1234567' or 'nm1234567'
     *
     * @param string $id
     */
    public function __construct(string $id, array $options = [])
    {
        // Don't validate here - let the specific parse methods validate
        $this->id = $id;
        $this->options = $options;
    }

    /**
     * Run the parser against the IMDB title HTML DOM
     *
     * @return Parser
     */
    public function runParser() : Parser
    {
        $dom = new Dom;

        $page = $dom->fetch("/title/{$this->id}/", $this->options);

        // set the properties
        $this->properties['id'] = $this->id;
        $this->properties['link'] = $this->getPermalink($page);
        $this->properties['metadata'] = $this->getMetadata($page);
        $this->properties['isTvSeries'] = $this->isTvSeries($page);
        $this->properties['title'] = $this->getTitle($page);
        $this->properties['originalTitle'] = $this->getOriginalTitle($page);
        $this->properties['year'] = $this->getYear($page);
        $this->properties['length'] = $this->getLength($page);
        $this->properties['rating'] = $this->getRating($page);
        $this->properties['ratingVotes'] = $this->getRatingVotes($page);
        $this->properties['popularityScore'] = $this->getPopularityScore($page);
        $this->properties['metaScore'] = $this->getMetaScore($page);
        $this->properties['genres'] = $this->getGenres($page);
        $this->properties['posterUrl'] = $this->getPosterUrl($page);
        $this->properties['trailerUrl'] = $this->getTrailerUrl($page);
        $this->properties['plot'] = $this->getPlot($page);
        $this->properties['actors'] = $this->getActors($page);
        $this->properties['similars'] = $this->getSimilars($page);
        $this->properties['seasonRefs'] = $this->getSeasonNumbers($page);

        // if it's a series, and "seasons" options is true, then fetch the episodes for each season
        if (
            $this->properties['isTvSeries'] &&
            array_key_exists('seasons', $this->options) &&
            $this->options['seasons'] &&
            count($this->properties['seasonRefs']) > 0
        ) {
            $seasons = [];
            foreach ($this->properties['seasonRefs'] as $seasonNumber) {
                $page = $dom->fetch("/title/{$this->id}/episodes/?season={$seasonNumber}", $this->options);
                $episodes = $this->getEpisodes($page);

                // if there are episodes, add them to the seasons array
                if (count($episodes) > 0) {
                    $seasons[] = [
                        'id' => "S" . str_pad($seasonNumber, 2, '0', STR_PAD_LEFT),
                        'number' => $seasonNumber,
                        'episodes' => $episodes,
                    ];
                }
            }
            $this->properties['seasons'] = $seasons;
        }

        // if the options tell us to fetch the credits, then do it
        if (array_key_exists('credits', $this->options) && $this->options['credits']) {
            $page = $dom->fetch("/title/{$this->id}/fullcredits", $this->options);
            $this->properties['credits'] = $this->getCredits($page);
        }

        // if the options tell us to fetch the awards, then do it
        if (array_key_exists('awards', $this->options) && $this->options['awards']) {
            $page = $dom->fetch("/title/{$this->id}/awards", $this->options);
            $this->properties['awards'] = $this->getAwards($page);
        }

        return $this;
    }

    /**
     * Get the properties
     *
     * @return array
     */
    public function toArray() : array
    {
        return $this->properties;
    }

    /**
     * Gets a Title instance from the properties
     *
     * @return Title
     */
    public function toTitle() : Title
    {
        return Title::newFromArray($this->properties);
    }

    /**
     * Gets the permalink of the movie or TV show, by parsing the <meta property="og:url" content="..." />
     *
     * @param HtmlDomParser $dom
     *
     * @return string
     */
    public function getPermalink(HtmlDomParser $dom) : string
    {
        $meta = $dom->findOneOrFalse('meta[property="og:url"]');
        if ($meta) {
            return $meta->getAttribute('content');
        }

        return "https://www.imdb.com/title/{$this->id}/";
    }

    /**
     * Get the metadata of the movie or TV show, by parsing the JSON-LD script tag
     *
     * @param HtmlDomParser $dom
     *
     * @return array
     */
    public function getMetadata(HtmlDomParser $dom) : array
    {
        $scripts = $dom->findMultiOrFalse('script');
        foreach ($scripts as $script) {
            if ($script->getAttribute('type') === 'application/ld+json') {
                $json = json_decode($script->innerText(), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $json;
                }
            }
        }
        return [];
    }

    /**
     * Get the "is series" boolean value, determining if the title is a series or not
     *
     * @param HtmlDomParser $dom
     *
     * @return bool
     */
    public function isTvSeries(HtmlDomParser $dom) : bool
    {
        $infoContainer = self::getInfoContainer($dom);
        if ($infoContainer) {
            $listItems = $infoContainer->find('li');
            foreach ($listItems as $item) {
                $text = self::clean($item->innerText());

                // if the text is "TV Series" or "Serie TV", then it's a series
                if (preg_match('/(TV Series|Serie TV)/i', $text, $matches)) {
                    return true;
                }
            }
        }

        $type = $this->getMetadataProp('@type');
        return ($type === 'TVSeries' || $type === 'TVSeason');
    }

    /**
     * Get the title of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return string
     */
    public function getTitle(HtmlDomParser $dom) : string
    {
        $heroContainer = self::getHeroContainer($dom);
        if ($heroContainer) {
            $title = $heroContainer->findOneOrFalse('h1[data-testid="hero__pageTitle"] span');
            if ($title) {
                return self::clean($title->innerText());
            }
        }

        return $this->getMetadataProp('name') ?? '';
    }

    /**
     * Get the original title of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return string
     */
    public function getOriginalTitle(HtmlDomParser $dom) : string
    {
        $heroContainer = self::getHeroContainer($dom);
        if ($heroContainer) {
            $title = $heroContainer->findOneOrFalse('h1[data-testid="hero__pageTitle"]');
            if ($title) {
                // find the closest parent <div> of the title
                $parent = $title->parentNode();

                // if the parent has a <div>, and it contains the text "Original title" or "Titolo originale", then we can assume the next sibling is the original title
                $div = $parent->findOneOrFalse('div');

                if ($div && (stripos($div->innerText(), 'original title') !== false || stripos($div->innerText(), 'titolo originale') !== false)) {
                    return self::clean(preg_replace('/(Original title|Titolo originale)\s*:?\s*/i', '', $title->innerText()));
                }
            }
        }

        // fallback: return the title
        return $this->getTitle($dom);
    }

    /**
     * Get the Year of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return int|null
     */
    public function getYear(HtmlDomParser $dom) : ?int
    {
        $infoContainer = self::getInfoContainer($dom);
        if ($infoContainer) {
            $listItems = $infoContainer->find('li');
            foreach ($listItems as $item) {
                $text = self::clean($item->innerText());

                if (preg_match('/([0-9]{4})(\s*[–\-]\s*([0-9]{4}))?/ui', $text, $matches)) {
                    return (int) $matches[1];
                }
            }
        }

        $datePublished = $this->getMetadataProp('datePublished');
        if ($datePublished) {
            return (int) substr($datePublished, 0, 4);
        }

        return null;
    }

    /**
     * Get the length of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return string|null
     */
    public function getLength(HtmlDomParser $dom) : ?string
    {
        $infoContainer = self::getInfoContainer($dom);
        if ($infoContainer) {
            $listItems = $infoContainer->find('li');
            foreach ($listItems as $item) {
                $text = self::clean($item->innerText());
                if (preg_match("/([0-9]+[h|m]\s*[0-9]+[h|m])|([0-9]+[h|m])/ui", $text, $matches)) {
                    return $matches[0];
                }
            }
        }

        $duration = $this->getMetadataProp('duration');
        if ($duration) {
            $duration = str_ireplace(['PT', 'H', 'M'], ['', 'h ', 'm '], $duration);
            return trim($duration);
        }

        return null;
    }

    /**
     * Get the rating of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return float|null
     */
    public function getRating(HtmlDomParser $dom) : ?float
    {
        $heroContainer = self::getHeroContainer($dom);
        if ($heroContainer) {
            $rating = $heroContainer->findOneOrFalse('div[data-testid="hero-rating-bar__aggregate-rating__score"] span');
            if ($rating) {
                return (float) str_replace(',', '.', self::clean($rating->innerText()));
            }
        }

        return $this->getMetadataProp('aggregateRating.ratingValue') ?? null;
    }

    /**
     * Get the number of votes for the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return int|null
     */
    public function getRatingVotes(HtmlDomParser $dom) : ?int
    {
        $heroContainer = self::getHeroContainer($dom);
        if ($heroContainer) {
            $ratingContainer = $heroContainer->findOneOrFalse('div[data-testid="hero-rating-bar__aggregate-rating__score"]');
            if ($ratingContainer) {
                // get the parent <div> of the rating container, then the last <div>
                $votesContainer = $ratingContainer->parentNode()->find('div', -1);
                if ($votesContainer) {
                    $text = self::clean($votesContainer->innerText());
                    return self::normalizeToInt($text);
                }
            }
        }

        return $this->getMetadataProp('aggregateRating.ratingCount') ?? null;
    }

    /**
     * Get the Popularity Score of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return int|null
     */
    public function getPopularityScore(HtmlDomParser $dom) : ?int
    {
        $heroContainer = self::getHeroContainer($dom);
        if ($heroContainer) {
            $popularityScore = $heroContainer->findOneOrFalse('div[data-testid="hero-rating-bar__popularity__score"]');
            if ($popularityScore) {
                return (int) str_replace(',', '.', self::clean($popularityScore->innerText()));
            }
        }

        return null;
    }

    /**
     * Get the MetaScore of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return int|null
     */
    public function getMetaScore(HtmlDomParser $dom) : ?int
    {
        $heroContainer = self::getHeroContainer($dom);
        if ($heroContainer) {
            $listContainer = $heroContainer->findOneOrFalse('ul[data-testid="reviewContent-all-reviews"]');
            if ($listContainer) {
                $listItems = $listContainer->find('li');
                foreach ($listItems as $item) {
                    $spans = $item->find('span.three-Elements span');
                    if (count((array) $spans) > 0) {
                        foreach ($spans as $i => $span) {
                            $text = self::clean($span->innerText());
                            if (strtolower($text) === 'metascore') {
                                return (int) self::clean($spans[$i - 1]->innerText());
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get the genres of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return array
     */
    public function getGenres(HtmlDomParser $dom) : array
    {
        $heroContainer = self::getHeroContainer($dom);
        if ($heroContainer) {
            $interestsContainer = $heroContainer->findOneOrFalse('div[data-testid="interests"]');
            $genres = $interestsContainer->find('a.ipc-chip span');
            if (count((array) $genres) > 0) {
                return array_map(function ($genre) {
                    return self::clean($genre->innerText());
                }, (array) $genres);
            }
        }

        return $this->getMetadataProp('genre') ?? [];
    }

    /**
     * Get the Poster URL of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return string|null
     */
    public function getPosterUrl(HtmlDomParser $dom) : ?string
    {
        $heroContainer = self::getHeroContainer($dom);
        if ($heroContainer) {
            $posterContainer = $heroContainer->findOneOrFalse('div[data-testid="hero-media__poster"]');
            if ($posterContainer) {
                // first img.ipc-image has the poster
                $poster = $posterContainer->findOneOrFalse('img.ipc-image');
                if ($poster) {
                    $posterUrl = $poster->getAttribute('src');
                    return self::formatHighQualityPosterUrl($posterUrl);
                }
            }
        }

        $metaImage = $this->getMetadataProp('image');
        if ($metaImage) {
            return self::formatHighQualityPosterUrl($metaImage);
        }

        return null;
    }

    /**
     * Get the Trailer URL of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return string|null
     */
    public function getTrailerUrl(HtmlDomParser $dom) : ?string
    {
        $heroContainer = self::getHeroContainer($dom);
        if ($heroContainer) {
            $trailerContainer = $heroContainer->findOneOrFalse('a[data-testid="video-player-slate-overlay"]');
            if ($trailerContainer) {
                $href = $trailerContainer->getAttribute('href');
                if (strpos($href, '/') === 0) {
                    return self::absolutizeUrl($href);
                }

                return $href;
            }
        }

        return $this->getMetadataProp('trailer.url') ?? null;
    }

    /**
     * Get the Plot of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return string
     */
    public function getPlot(HtmlDomParser $dom) : string
    {
        $heroContainer = self::getHeroContainer($dom);
        if ($heroContainer) {
            $plotContainer = $heroContainer->findOneOrFalse('p[data-testid="plot"]');
            if ($plotContainer) {
                // use span[data-testid="plot-xl"] if available, else, use the first span
                $plot = $plotContainer->findOneOrFalse('span[data-testid="plot-xl"]') ?? $plotContainer->findOneOrFalse('span');
                if ($plot) {
                    return self::clean(
                        self::truncateBeforeTruncate(
                            $plot->innerText()
                        )
                    );
                }
            }
        }

        return $this->getMetadataProp('description') ?? '';
    }

    /**
     * Get the Actors of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return array
     */
    public function getActors(HtmlDomParser $dom) : array
    {
        $castContainer = self::getCastContainer($dom);
        if ($castContainer) {
            $castElements = $castContainer->find('div[data-testid="title-cast-item"]');
            if (count((array) $castElements) > 0) {
                $cast = [];
                foreach ($castElements as $element) {
                    $imgElement = $element->findOneOrFalse('img');
                    $actorElement = $element->findOneOrFalse('a[data-testid="title-cast-item__actor"]');
                    $characterElement = $element->findOneOrFalse('a[data-testid="cast-item-characters-link"]');

                    $img = $imgElement ? self::formatHighQualityPosterUrl($imgElement->getAttribute('src')) : null;
                    $actor = $actorElement ? self::clean($actorElement->innerText()) : null;
                    $link = $actorElement ? self::absolutizeUrl($actorElement->getAttribute('href')) : null;
                    $character = $characterElement ? self::clean($characterElement->innerText()) : null;

                    $id = null;
                    if ($link && preg_match('/\/name\/(nm[0-9]{7,8})\//', $link, $matches)) {
                        $id = $matches[1];
                    }

                    $cast[] = [
                        'type' => Person::TYPE_ACTOR,
                        'id' => $id,
                        'image' => $img,
                        'name' => $actor,
                        'link' => $link,
                        'character' => $character,
                    ];
                }
                return $cast;
            }
        }

        $cast = $this->getMetadataProp('actor.*.name');
        if (is_array($cast) && count($cast) > 0) {
            return array_map(function ($actor) {
                return [
                    'type' => Person::TYPE_ACTOR,
                    'id' => null,
                    'image' => null,
                    'name' => $actor,
                    'link' => null,
                    'character' => null,
                ];
            }, $cast);
        }

        return [];
    }

    /**
     * Get the Similars of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return array
     */
    public function getSimilars(HtmlDomParser $dom) : array
    {
        $similarsContainer = $dom->findOneOrFalse('section.ipc-page-section[data-testid="MoreLikeThis"]');
        if ($similarsContainer) {
            $similarsElements = $similarsContainer->find('div.ipc-poster-card[role="group"]');
            if (count((array) $similarsElements) > 0) {
                $similars = [];
                foreach ($similarsElements as $element) {
                    $linkElement = $element->findOneOrFalse('a.ipc-poster-card__title');

                    $title = $linkElement ? self::clean($linkElement->find('span[data-testid="title"]', 0)->innerText()) : null;
                    $link = $linkElement ? self::absolutizeUrl($linkElement->getAttribute('href')) : null;

                    $id = null;
                    if ($link && preg_match('/\/title\/(tt[0-9]{7,8})\//', $link, $matches)) {
                        $id = $matches[1];
                    }

                    $similars[] = [
                        'id' => $id,
                        'title' => $title,
                        'link' => $link,
                    ];
                }
                return $similars;
            }
        }

        return [];
    }

    /**
     * Get the Credits of the movie or TV show
     *
     * @param HtmlDomParser $dom
     *
     * @return array
     */
    public function getCredits(HtmlDomParser $dom) : array
    {
        $sections = $dom->findMultiOrFalse('.ipc-page-section.ipc-page-section--base');
        $credits = [];
        if (!$sections) {
            return [];
        }

        // Cast, Secondary Directors, Music
        foreach ($sections as $section) {
            $collectionElement = $section->findOneOrFalse('h3 span');
            if (!$collectionElement) {
                // if there is no collection element, skip this section
                continue;
            }
            
            $role = self::clean($collectionElement->innerText());

            if (str_ireplace([
                'contribute',
                'title',
                'contribuisci',
                'titolo',
                'contribuer',
                'titre',
                'beitragen',
                'titel',
                'contribua',
                'título'
            ], '', $role) !== $role) {
                // If the category is a generic "contribute to this page" or "more from this title", skip it
                continue;
            }

            $entries = $section->findMultiOrFalse('li.ipc-metadata-list-summary-item');
            if (!$entries) {
                // if there are no entries, skip this section
                continue;
            }

            // Each Secondary Director
            foreach ($entries as $entry) {
                // Name
                $personElement = $entry->findOneOrFalse('a.name-credits--title-text-big');
                if (!$personElement) {
                    // if there is no person element, skip this entry
                    continue;
                }

                $name = self::clean($personElement->innerText());
                $link = self::absolutizeUrl($personElement->getAttribute('href'));

                $id = null;
                if (preg_match('/\/name\/(nm\d+)/', $link, $matches)) {
                    $id = $matches[1];
                }

                if (empty($id)) {
                    // if the ID is empty, skip this entry
                    continue;
                }

                // Image
                $imageElement = $entry->findOneOrFalse('img');
                $image = $imageElement ? self::formatHighQualityPosterUrl($imageElement->getAttribute('src')) : null;

                // Parse the Person as a character by default
                $character = null;
                $involvement = null;
                $type = null;
                $entryHtml = $entry->innerHtml();
                if (preg_match('~(\/title\/.*?\/characters\/(.*?)\/).*?>(.*?)<~', $entryHtml, $matches)) {
                    // If this Person is a character, assign the involvement as 'character' and swap the ID and link
                    $involvement = 'character';
                    $id = $matches[2];
                    $type = Person::TYPE_ACTOR;
                    $character = self::clean($matches[3]);
                    $link = self::absolutizeUrl($matches[1]);
                } elseif (preg_match('~<div class=".*?"><span>(.*?)<~', $entryHtml, $matches)) {
                    // If this Person is not a character, then its involvement is inherited from the inner HTML itself
                    $involvement  = $matches[1];
                }

                $credit = [
                    'role' => $role,
                    'involvement' => $involvement,
                    'person' => [
                        'id' => $id,
                        'type' => $type,
                        'name' => $name,
                        'link' => $link,
                        'image' => $image,
                        'character' => $character,
                    ]
                ];
                
                $credits[] = $credit;
            }
        }

        return $credits;
    }

    /**
     * Get the Seasons Numbers of the TV show (if it's a series)
     *
     * @param HtmlDomParser $dom
     *
     * @return array
     */
    public function getSeasonNumbers(HtmlDomParser $dom) : array
    {
        $isTvSeries = $this->properties['isTvSeries'];
        if (!$isTvSeries) {
            return [];
        }

        $seasonsContainer = $dom->findOneOrFalse('div[data-testid="episodes-browse-episodes"]');
        if ($seasonsContainer) {
            $seasonsElements = $seasonsContainer->find('select#browse-episodes-season option');
            if (count((array) $seasonsElements) > 0) {
                $seasons = array_values(array_filter(array_map(function ($element) {
                    $value = intval($element->getAttribute('value'));
                    return ($value > 0) ? $value : null;
                }, (array) $seasonsElements)));

                // sort the seasons in ascending order
                sort($seasons, SORT_NUMERIC);

                return $seasons;
            } else {
                // fallback searching <a> elements that match /title/tt[0-9]+/episodes?season=
                $seasonsElements = $seasonsContainer->find('a');
                if (count((array) $seasonsElements) > 0) {
                    $seasons = array_values(array_filter(array_map(function ($element) {
                        $href = $element->getAttribute('href');
                        if (preg_match('/\/title\/tt[0-9]+\/episodes\/?\?season=([0-9]+)/', $href, $matches)) {
                            return (int) $matches[1];
                        }
                        return null;
                    }, (array) $seasonsElements)));

                    // sort the seasons in ascending order
                    sort($seasons, SORT_NUMERIC);

                    return $seasons;
                }
            }
        }

        return [];
    }

    /**
     * Get the Episodes of the TV show (if it's a series)
     *
     * @param HtmlDomParser $dom
     *
     * @return array
     */
    public function getEpisodes(HtmlDomParser $dom) : array
    {
        $episodes = [];
        $episodeContainers = $dom->find('article.episode-item-wrapper');
        if (count((array) $episodeContainers) > 0) {
            foreach ($episodeContainers as $container) {
                $imgElement = $container->findOneOrFalse('img.ipc-image');
                $titleElement = $container->findOneOrFalse('h4[data-testid="slate-list-card-title"]');
                $linkElement = $container->findOneOrFalse('h4[data-testid="slate-list-card-title"] a');
                $airDateElement = ($titleElement) ? $titleElement->parentNode()->findOneOrFalse('span') : null;
                $plotElement = $container->findOneOrFalse('div.ipc-html-content.ipc-html-content--base.ipc-html-content--display-inline[role="presentation"]');
                $ratingContainer = $container->findOneOrFalse('div[data-testid="ratingGroup--container"]');

                $img = $imgElement ? self::formatHighQualityPosterUrl($imgElement->getAttribute('src')) : null;
                $title = $titleElement ? self::clean($titleElement->innerText()) : null;
                $link = $linkElement ? self::absolutizeUrl($linkElement->getAttribute('href')) : null;
                $airDate = $airDateElement ? self::normalizeToDate(self::clean($airDateElement->innerText())) : null;
                $plot = $plotElement ? self::clean($plotElement->innerText()) : null;
                // Fixed: use findOneOrFalse to prevent errors when elements don't exist
                $ratingElement = $ratingContainer ? $ratingContainer->findOneOrFalse('span.ipc-rating-star--rating') : null;
                $rating = $ratingElement ? self::clean($ratingElement->innerText()) : null;
                
                $votesElement = $ratingContainer ? $ratingContainer->findOneOrFalse('span.ipc-rating-star--voteCount') : null;
                $ratingVotes = $votesElement ? self::normalizeToInt(self::clean($votesElement->innerText())) : null;

                // match the season and episode number from the title (S8.E1 ∙ The Locomotion Interruption)
                $seasonNumber = null;
                $episodeNumber = null;
                if ($title && preg_match('/s([0-9]+)[\s\.\-]*e([0-9]+)/i', $title, $matches)) {
                    $seasonNumber = (int) $matches[1];
                    $episodeNumber = (int) $matches[2];

                    // remove the full match from the title
                    $title = str_replace($matches[0], '', $title);

                    // remove the bullet and any leading/trailing spaces
                    $title = trim(str_replace('∙', '', $title));
                }

                // extract the IMDB ID from the link
                $id = null;
                if ($link && preg_match('/\/title\/(tt[0-9]{7,8})\//', $link,  $matches)) {
                    $id = $matches[1];
                }

                $episodes[] = [
                    'id' => $id,
                    'img' => $img,
                    'title' => $title,
                    'link' => $link,
                    'seasonNumber' => $seasonNumber,
                    'episodeNumber' => $episodeNumber,
                    'airDate' => $airDate,
                    'plot' => $plot,
                    'rating' => (strpos($rating, ',') !== false) ? (float) str_replace(',', '.', $rating) : (float) $rating,
                    'ratingVotes' => $ratingVotes,
                ];
            }
        }

        return $episodes;
    }

    /**
     * Get a Metadata Property, navigating through the LD-JSON metadata with dot notation.
     *
     * @param string $dotNotationProp The dot notation string (e.g., "actor.*.name").
     * @param array|null $currMetadata Optional current metadata to search in.
     * @return mixed The extracted metadata or null if not found.
     */
    private function getMetadataProp(string $dotNotationProp, ?array $currMetadata = null)
    {
        $keys = explode('.', $dotNotationProp);
        $metadata = ($currMetadata) ? $currMetadata : $this->properties['metadata'] ?? [];

        foreach ($keys as $i => $key) {
            if (is_array($metadata) && !array_key_exists($key, $metadata)) {
                $metadata = [];
            } elseif (is_array($metadata) && array_key_exists($key, $metadata)) {
                $metadata = $metadata[$key];
            } elseif (is_array($metadata) && $key === '*') {
                $innerKeys = array_slice($keys, $i + 1);
                $metadata = array_map(function ($item) use ($innerKeys) {
                    return $this->getMetadataProp(implode('.', $innerKeys), $item);
                }, $metadata);
            }
        }

        if (empty($metadata)) {
            return null;
        }

        return $metadata;
    }

    /**
     * Get the hero container
     *
     * @param HtmlDomParser $dom
     *
     * @return SimpleHtmlDom|null
     */
    private static function getHeroContainer(HtmlDomParser $dom) : ?SimpleHtmlDom
    {
        $elements = $dom->find('section.ipc-page-section[data-testid="hero-parent"]');

        if (count((array) $elements) === 0) {
            return null;
        }

        return array_values((array) $elements)[0];
    }

    /**
     * Get the Cast container
     *
     * @param HtmlDomParser $dom
     *
     * @return SimpleHtmlDom|null
     */
    private static function getCastContainer(HtmlDomParser $dom) : ?SimpleHtmlDom
    {
        $elements = $dom->find('section.ipc-page-section[data-testid="title-cast"]');

        if (count((array) $elements) === 0) {
            return null;
        }

        return array_values((array) $elements)[0];
    }

    /**
     * Get the info container UL
     *
     * @param HtmlDomParser $dom
     *
     * @return SimpleHtmlDom|null
     */
    private static function getInfoContainer(HtmlDomParser $dom) : ?SimpleHtmlDom
    {
        $heroContainer = self::getHeroContainer($dom);
        if ($heroContainer) {
            $listContainers = $heroContainer->find('ul.ipc-inline-list[role="presentation"]');
            if ($listContainers) {
                // among all listContainers, find the one who does not have data-testid="hero-subnav-bar-topic-links"
                $listContainer = array_values(array_filter((array) $listContainers, function ($container) {
                    return !$container->hasAttribute('data-testid') || $container->getAttribute('data-testid') !== 'hero-subnav-bar-topic-links';
                }));

                if (count($listContainer) > 0) {
                    return $listContainer[0];
                }
            }
        }

        return null;
    }

    /**
     * Clean up the string
     *
     * @param string $string
     *
     * @return string
     */
    private static function clean(string $string) : string
    {
        $string = str_replace(chr(194).chr(160), '', html_entity_decode(trim($string), ENT_QUOTES));
        return trim(preg_replace('/\s\s+/', ' ', strip_tags($string)));
    }

    /**
     * Truncate string before "..." and back to the last complete sentence
     *
     * @param string $string
     *
     * @return string
     */
    private static function truncateBeforeTruncate(string $string) : string
    {
        // Find the position of "..." and remove everything from that point onwards
        $ellipsisPos = strpos($string, '...');
        if ($ellipsisPos !== false) {
            $string = substr($string, 0, $ellipsisPos);
        }

        // Find the last complete sentence by looking for sentence-ending punctuation
        // followed by a space or end of string
        $lastSentenceEnd = 0;
        
        for ($i = 0; $i < strlen($string); $i++) {
            $char = $string[$i];
            if (in_array($char, self::SENTENCE_PUNCTUATION)) {
                // Check if this is followed by a space or is at the end
                if ($i === strlen($string) - 1 || $string[$i + 1] === ' ') {
                    $lastSentenceEnd = $i + 1;
                }
            }
        }

        // If we found a sentence ending, truncate to that point
        if ($lastSentenceEnd > 0) {
            $string = substr($string, 0, $lastSentenceEnd);
        }

        return trim($string);
    }

    /**
     * Absolutizes an IMDB URL, if it's relative
     *
     * @param string $url
     *
     * @return string
     */
    private static function absolutizeUrl(string $url) : string
    {
        if (strpos($url, '/') === 0) {
            $url = "https://www.imdb.com$url";
        }

        // remove any query string
        $url = explode('?', $url)[0];

        return $url;
    }

    /**
     * Format poster URL to get a high-quality version
     * Extracts the image ID and formats it to the high-quality template
     *
     * @param string|null $url
     *
     * @return string|null
     */
    private static function formatHighQualityPosterUrl(?string $url) : ?string
    {
        if (empty($url)) {
            return null;
        }

        // Extract the image ID from the URL
        // Handle various formats:
        // - With @ signs: MV5BOWJmYTNiMWUtMDUyYS00MjE4LWIzY2MtMzMxN2QxODU1ZDVmXkEyXkFqcGc@._V1_...
        // - With @@ signs: MV5BMTc4MTAyNzMzNF5BMl5BanBnXkFtZTcwMzQ5MzQzMg@@._V1_...
        // - Without @ signs: MV5BMTI2NTY0NzA4MF5BMl5BanBnXkFtZTYwMjE1MDE0._V1_...

        // First try to match patterns with @ signs
        if (preg_match('/\/(MV5[A-Za-z0-9]+)([@]+)(\.)?_V1/', $url, $matches)) {
            $imageId = $matches[1];
            $atSigns = $matches[2]; // Preserve the original number of @ signs
            // Return high-quality format with preserved @ pattern
            return "https://m.media-amazon.com/images/M/{$imageId}{$atSigns}._V1_FMjpg_QL100_.jpg";
        }

        // Then try patterns without @ signs (just ._V1)
        if (preg_match('/\/(MV5[A-Za-z0-9]+)\._V1/', $url, $matches)) {
            $imageId = $matches[1];
            // For URLs without @, keep them without @ in the formatted URL
            return "https://m.media-amazon.com/images/M/{$imageId}._V1_FMjpg_QL100_.jpg";
        }

        // If we can't extract the ID, return the absolutized original URL
        return self::absolutizeUrl($url);
    }

    /**
     * Parses a numeric string (e.g., "1.5M") and returns the integer value
     *
     * @param string $text
     *
     * @return int|null
     */
    private static function normalizeToInt(string $text) : ?int
    {
        // match any [m] or [mln] or [k] and convert them to millions or thousands
        if (preg_match('/([0-9,.]+)\s*(m|mln|k)?/i', $text, $matches)) {
            $votes = (float) str_replace(',', '.', $matches[1]);
            $multiplier = strtolower($matches[2] ?? '');

            if ($multiplier === 'm' || $multiplier === 'mln') {
                return (int) ($votes * 1000000);
            } elseif ($multiplier === 'k') {
                return (int) ($votes * 1000);
            } else {
                return (int) $votes;
            }
        }

        return null;
    }

    /**
     * Parses a date as Y-m-d
     *
     * @param string $date
     *
     * @return string|null
     */
    private static function normalizeToDate(string $date) : ?string
    {
        $patterns = [
            'EEE, MMM dd, yyyy',        // Mon, Sep 22, 2014
            'EEE, dd MMM yyyy',         // lun, 22 sept 2014
            'EEE, dd \'de\' MMM \'de\' yyyy', // seg., 22 de set. de 2014
            'EEE, dd MMM yyyy',         // dom, 21 set 2014
        ];

        foreach ($patterns as $pattern) {
            $formatter = new \IntlDateFormatter(
                'en_US', // Neutral locale, it works for various inputs
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::FULL,
                'UTC',
                \IntlDateFormatter::GREGORIAN,
                $pattern
            );

            $timestamp = $formatter->parse($date);
            if ($timestamp !== false) {
                return (new \DateTime())->setTimestamp($timestamp)->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Validate the IMDB identifier
     *
     * @param string $id
     *
     * @return void
     */
    private static function validateId(string $id) : void
    {
        // throw an exception if the identifier is not in the correct format
        if (!preg_match('/^tt[0-9]{7,8}$/', $id)) {
            throw new \Exception("Mfonte\ImdbScraper\Parser:: Invalid IMDB identifier provided: $id. Must be in the form of 'tt1234567'");
        }
    }

    /**
     * Get the awards and festival nominations/wins from the awards page
     *
     * @param HtmlDomParser $dom
     *
     * @return array
     */
    public function getAwards(HtmlDomParser $dom) : array
    {
        $awards = [];
        
        // First check if there's JSON-LD data with awards
        $scripts = $dom->findMultiOrFalse('script[type="application/json"]');
        if ($scripts) {
            foreach ($scripts as $script) {
                $jsonContent = $script->innerText();
                if (strpos($jsonContent, '__NEXT_DATA__') !== false || strpos($jsonContent, 'categories') !== false) {
                    $data = json_decode($jsonContent, true);
                    if ($data && isset($data['props']['pageProps']['data']['categories'])) {
                        $categories = $data['props']['pageProps']['data']['categories'];
                        foreach ($categories as $category) {
                                $festivalData = [
                                'id' => $category['id'] ?? null,
                                'name' => $category['name'] ?? null,
                                'awards' => []
                            ];
                        
                            if (isset($category['section']['items'])) {
                                foreach ($category['section']['items'] as $item) {
                                        $award = [
                                        'category' => isset($item['listContent'][0]['text']) ? $item['listContent'][0]['text'] : null,
                                        'year' => null,
                                        'outcome' => null,
                                        'recipients' => []
                                        ];
                                    
                                    // Extract year and outcome from rowTitle/rowSubTitle
                                    if (isset($item['rowTitle'])) {
                                        if (preg_match('/(\d{4})\s*(Winner|Nominee)?/i', $item['rowTitle'], $matches)) {
                                            $award['year'] = (int)$matches[1];
                                            if (isset($matches[2])) {
                                                $award['outcome'] = $matches[2];
                                            }
                                        }
                                    }
                                
                                    if (isset($item['rowSubTitle'])) {
                                        $award['description'] = $item['rowSubTitle'];
                                    }
                                
                                    // Extract recipients
                                    if (isset($item['subListContent'])) {
                                        foreach ($item['subListContent'] as $recipient) {
                                            $award['recipients'][] = [
                                                'name' => $recipient['text'] ?? null,
                                                'href' => $recipient['href'] ?? null
                                            ];
                                        }
                                    }
                                
                                    $festivalData['awards'][] = $award;
                                }
                            }
                            
                            $awards[] = $festivalData;
                        }
                    }
                }
            }
        }
        
        // If no JSON data found, try parsing HTML structure
        if (empty($awards)) {
            $sections = $dom->findMultiOrFalse('section.ipc-page-section');
            if ($sections) {
                foreach ($sections as $section) {
                    $titleElement = $section->findOneOrFalse('h3.ipc-title__text');
                    if ($titleElement) {
                        $festivalName = self::clean($titleElement->innerText());
                        $festivalData = [
                            'name' => $festivalName,
                            'awards' => []
                        ];
                    
                        $awardItems = $section->findMultiOrFalse('li.ipc-metadata-list-summary-item');
                        if ($awardItems) {
                            foreach ($awardItems as $item) {
                                $categoryElement = $item->findOneOrFalse('.awardCategoryName');
                                $titleLinkElement = $item->findOneOrFalse('a.ipc-metadata-list-summary-item__t');
                        
                                if ($categoryElement && $titleLinkElement) {
                                    $award = [
                                        'category' => self::clean($categoryElement->innerText()),
                                        'year' => null,
                                        'outcome' => null,
                                        'recipients' => []
                                    ];
                            
                                    $titleText = self::clean($titleLinkElement->innerText());
                                    if (preg_match('/(\d{4})\s*(Winner|Nominee)?/i', $titleText, $matches)) {
                                        $award['year'] = (int)$matches[1];
                                        if (isset($matches[2])) {
                                            $award['outcome'] = $matches[2];
                                        }
                                    }
                            
                                    // Get recipients
                                    $recipientElements = $item->findMultiOrFalse('ul.ipc-metadata-list-summary-item__stl a');
                                    if ($recipientElements) {
                                        foreach ($recipientElements as $recipient) {
                                            $award['recipients'][] = [
                                                'name' => self::clean($recipient->innerText()),
                                                'href' => self::absolutizeUrl($recipient->getAttribute('href'))
                                            ];
                                        }
                                    }
                                    
                                    $festivalData['awards'][] = $award;
                                }
                            }
                        }
                        
                        if (!empty($festivalData['awards'])) {
                            $awards[] = $festivalData;
                        }
                    }
                }
            }
        }
        
        return $awards;
    }

    /**
     * Run the parser against a person profile page
     *
     * @return Parser
     */
    public function runPersonParser() : Parser
    {
        $dom = new Dom;
        $page = $dom->fetch("/name/{$this->id}/", $this->options);

        // Parse person data
        $this->properties = [
            'id' => $this->id,
            'link' => "https://www.imdb.com/name/{$this->id}/",
            'name' => $this->getPersonName($page),
            'image' => $this->getPersonImage($page),
            'bio' => $this->getPersonBio($page),
            'birthDate' => $this->getPersonBirthDate($page),
            'birthPlace' => $this->getPersonBirthPlace($page),
            'deathDate' => $this->getPersonDeathDate($page),
            'professions' => $this->getPersonProfessions($page),
            'knownFor' => $this->getPersonKnownFor($page),
            'otherNames' => $this->getPersonOtherNames($page),
        ];

        return $this;
    }

    /**
     * Convert properties to Person entity
     *
     * @return Person
     */
    public function toPerson() : Person
    {
        return Person::newFromArray($this->properties);
    }

    /**
     * Get person's name
     */
    private function getPersonName(HtmlDomParser $dom) : string
    {
        $nameElement = $dom->findOneOrFalse('h1[data-testid="hero__pageTitle"] span');
        if ($nameElement) {
            return self::clean($nameElement->innerText());
        }
        return '';
    }

    /**
     * Get person's image
     */
    private function getPersonImage(HtmlDomParser $dom) : ?string
    {
        // Look for hero section
        $heroSection = $dom->findOneOrFalse('section[data-testid*="hero"]');
        if (!$heroSection) {
            $heroSection = $dom->findOneOrFalse('div[class*="Hero"]');
        }
        
        if ($heroSection) {
            // Find all images in hero section
            $images = $heroSection->find('img.ipc-image');
            
            // Usually the first image is the profile photo
            if (count($images) > 0) {
                $firstImg = $images[0];
                $src = $firstImg->getAttribute('src');
                
                // Make sure it's not a tiny icon (should have UX or be a reasonable size)
                if ($src && (strpos($src, 'UX') !== false || strpos($src, 'UY') !== false || strpos($src, '_V1_') !== false)) {
                    return self::formatHighQualityPosterUrl($src);
                }
            }
            
            // If first image didn't work, try others
            foreach ($images as $img) {
                $src = $img->getAttribute('src');
                $alt = $img->getAttribute('alt');
                
                // Check if this looks like a person photo
                if ($src && (strpos($src, 'UX140') !== false || strpos($src, 'UX280') !== false || strpos($src, 'UY207') !== false)) {
                    return self::formatHighQualityPosterUrl($src);
                }
            }
        }
        
        // Fallback: look for any image with class ipc-image that's substantial
        $allImages = $dom->find('img.ipc-image');
        foreach ($allImages as $img) {
            $src = $img->getAttribute('src');
            // Look for images with reasonable dimensions
            if ($src && preg_match('/UX(\d+)/', $src, $matches)) {
                $width = intval($matches[1]);
                if ($width >= 140) {
                    return self::formatHighQualityPosterUrl($src);
                }
            }
        }
        
        return null;
    }

    /**
     * Get person's biography
     */
    private function getPersonBio(HtmlDomParser $dom) : ?string
    {
        // Try multiple selectors for bio
        $bioElement = $dom->findOneOrFalse('div[data-testid="bio-content"] div.ipc-html-content-inner-div');
        if (!$bioElement) {
            $bioElement = $dom->findOneOrFalse('section[data-testid="Storyline"] div.ipc-html-content-inner-div');
        }
        if (!$bioElement) {
            // Try to find any bio section
            $bioSection = $dom->findOneOrFalse('section:contains("Biography")');
            if ($bioSection) {
                $bioElement = $bioSection->findOneOrFalse('div.ipc-html-content-inner-div');
            }
        }
        if ($bioElement) {
            return self::clean($bioElement->innerText());
        }
        return null;
    }

    /**
     * Get person's birth date
     */
    private function getPersonBirthDate(HtmlDomParser $dom) : ?string
    {
        // First try the hero area birth container
        $birthElement = $dom->findOneOrFalse('div[data-testid="birth-and-death-birthdate"]');
        if ($birthElement) {
            // Extract text from spans
            $spans = $birthElement->find('span');
            foreach ($spans as $span) {
                $text = trim($span->innerText());
                // Look for date pattern
                if (preg_match('/([A-Za-z]+ \d+, \d{4})/', $text, $matches)) {
                    return $matches[1];
                }
            }
        }
        
        // Try PersonalDetails section
        $detailsSection = $dom->findOneOrFalse('section[data-testid="PersonalDetails"]');
        if ($detailsSection) {
            $listItems = $detailsSection->find('li');
            foreach ($listItems as $li) {
                $label = $li->findOneOrFalse('span.ipc-metadata-list-item__label');
                if ($label && strpos($label->innerText(), 'Born') !== false) {
                    $content = $li->findOneOrFalse('div.ipc-metadata-list-item__content-container');
                    if ($content) {
                        // Look for date links
                        $monthDayLink = $content->findOneOrFalse('a[href*="birth_monthday"]');
                        $yearLink = $content->findOneOrFalse('a[href*="birth_year"]');
                        
                        if ($monthDayLink && $yearLink) {
                            $monthDay = trim($monthDayLink->innerText());
                            $year = trim($yearLink->innerText());
                            return $monthDay . ', ' . $year;
                        }
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Get person's birth place
     */
    private function getPersonBirthPlace(HtmlDomParser $dom) : ?string
    {
        // Try PersonalDetails section
        $detailsSection = $dom->findOneOrFalse('section[data-testid="PersonalDetails"]');
        if ($detailsSection) {
            $listItems = $detailsSection->find('li');
            foreach ($listItems as $li) {
                $label = $li->findOneOrFalse('span.ipc-metadata-list-item__label');
                if ($label && strpos($label->innerText(), 'Born') !== false) {
                    $content = $li->findOneOrFalse('div.ipc-metadata-list-item__content-container');
                    if ($content) {
                        // Look for birth place link
                        $placeLink = $content->findOneOrFalse('a[href*="birth_place"]');
                        if ($placeLink) {
                            return self::clean($placeLink->innerText());
                        }
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Get person's death date
     */
    private function getPersonDeathDate(HtmlDomParser $dom) : ?string
    {
        $deathElement = $dom->findOneOrFalse('div[data-testid="birth-and-death-deathdate"]');
        if ($deathElement) {
            $timeElement = $deathElement->findOneOrFalse('time');
            if ($timeElement) {
                return $timeElement->getAttribute('datetime');
            }
        }
        return null;
    }

    /**
     * Get person's professions
     */
    private function getPersonProfessions(HtmlDomParser $dom) : array
    {
        $professions = [];
        
        // Look for navigation jump links that indicate professions
        $navLists = $dom->find('ul.ipc-inline-list');
        foreach ($navLists as $navList) {
            $items = $navList->find('li a');
            foreach ($items as $link) {
                $href = $link->getAttribute('href');
                $text = trim($link->innerText());
                
                // Check if this is a profession link
                if (preg_match('/#(actor|actress|director|producer|writer|composer|soundtrack|cinematographer|editor)/', $href, $matches)) {
                    if (!in_array($text, $professions) && $text && !is_numeric($text)) {
                        $professions[] = $text;
                    }
                }
            }
        }
        
        // If no professions found, check the metadata
        if (empty($professions)) {
            $metaOccupation = $dom->findOneOrFalse('span[itemprop="jobTitle"]');
            if ($metaOccupation) {
                $text = self::clean($metaOccupation->innerText());
                if ($text) {
                    $professions = explode(',', $text);
                    $professions = array_map('trim', $professions);
                }
            }
        }
        
        return $professions;
    }

    /**
     * Get person's known for (interests) - the main feature we're adding
     */
    private function getPersonKnownFor(HtmlDomParser $dom) : array
    {
        $knownFor = [];
        
        // Try the "Known For" section
        $knownForSection = $dom->findOneOrFalse('div[data-testid="nm_flmg_kwn_for"]');
        if (!$knownForSection) {
            // Alternative selector
            $knownForSection = $dom->findOneOrFalse('section[data-testid="Known For"]');
        }
        
        if ($knownForSection) {
            // Find all title links in the Known For section
            $titleLinks = $knownForSection->find('a[href*="/title/"]');
            $uniqueTitles = [];
            
            foreach ($titleLinks as $link) {
                $href = $link->getAttribute('href');
                if (preg_match('/\/title\/(tt\d+)/', $href, $matches)) {
                    $titleId = $matches[1];
                    
                    // Skip duplicates
                    if (isset($uniqueTitles[$titleId])) {
                        continue;
                    }
                    
                    // Get the aria-label which often contains the title
                    $titleText = $link->getAttribute('aria-label');
                    
                    // If no aria-label, try to get text from link or parent
                    if (!$titleText) {
                        // Try to find parent poster div
                        $parent = $link->parentNode();
                        while ($parent && !strpos($parent->getAttribute('class'), 'ipc-poster')) {
                            $parent = $parent->parentNode();
                        }
                        
                        if ($parent) {
                            // Look for title in various places
                            $imgElement = $parent->findOneOrFalse('img');
                            if ($imgElement) {
                                $alt = $imgElement->getAttribute('alt');
                                if ($alt) {
                                    // Extract title from alt text like "Morgan Freeman in Se7en (1995)"
                                    if (preg_match('/in\s+(.+?)\s*\(\d{4}\)/', $alt, $altMatches)) {
                                        $titleText = $altMatches[1];
                                    } else {
                                        $titleText = $alt;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Extract year from various sources
                    $year = null;
                    if (preg_match('/\((\d{4})\)/', $titleText, $yearMatches)) {
                        $year = $yearMatches[1];
                        // Clean year from title
                        $titleText = trim(preg_replace('/\s*\(\d{4}\)/', '', $titleText));
                    }
                    
                    // Extract role if present
                    $role = null;
                    if (strpos($titleText, ' in ') !== false) {
                        $parts = explode(' in ', $titleText);
                        if (count($parts) > 1) {
                            $role = trim($parts[0]);
                            $titleText = trim($parts[1]);
                        }
                    }
                    
                    $uniqueTitles[$titleId] = [
                        'id' => $titleId,
                        'title' => self::clean($titleText),
                        'year' => $year,
                        'role' => $role,
                        'link' => self::absolutizeUrl($href),
                    ];
                }
            }
            
            $knownFor = array_values($uniqueTitles);
        }
        
        return $knownFor;
    }

    /**
     * Get person's other names/aliases
     */
    private function getPersonOtherNames(HtmlDomParser $dom) : array
    {
        $otherNames = [];
        $detailsSection = $dom->findOneOrFalse('section[data-testid="Details"]');
        if ($detailsSection) {
            $items = $detailsSection->find('li[data-testid*="details"]');
            foreach ($items as $item) {
                $label = $item->findOneOrFalse('span');
                if ($label && strpos(strtolower($label->innerText()), 'also known as') !== false) {
                    $namesList = $item->find('li');
                    foreach ($namesList as $nameItem) {
                        $name = self::clean($nameItem->innerText());
                        if ($name) {
                            $otherNames[] = $name;
                        }
                    }
                }
            }
        }
        return $otherNames;
    }

    /**
     * Validate person ID format
     */
    private static function validatePersonId(string $id) : void
    {
        if (!preg_match('/^nm\d{7,8}$/', $id)) {
            throw new \InvalidArgumentException("Invalid person ID format: {$id}");
        }
    }
}
