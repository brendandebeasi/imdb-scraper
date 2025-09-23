<?php
namespace Mfonte\ImdbScraper\Entities;

/**
 * Class Person
 * Represents a Person in the IMDb database, such as an actor, director, writer, or producer.
 */
class Person extends Entity
{
    public const TYPE_ACTOR = 'actor';
    public const TYPE_DIRECTOR = 'director';
    public const TYPE_WRITER = 'writer';
    public const TYPE_PRODUCER = 'producer';

    /**
     * @var string The type of the Person (e.g., "actor", "director")
     */
    public $type;

    /**
     * @var string Unique IMDb ID (e.g., "nm0000226")
     */
    public $id;

    /**
     * @var string The name of the Person
     */
    public $name;

    /**
     * @var string The URL of the IMDb page
     */
    public $link;

    /**
     * @var string|Person The character played by the Person
     */
    public $character;

    /**
     * @var string The URL of the Person's image
     */
    public $image;
    
    /**
     * @var string Biography/description of the person
     */
    public $bio;
    
    /**
     * @var string Birth date
     */
    public $birthDate;
    
    /**
     * @var string Birth place
     */
    public $birthPlace;
    
    /**
     * @var string Death date (if applicable)
     */
    public $deathDate;
    
    /**
     * @var array Known for / interests (titles the person is famous for)
     */
    public $knownFor;
    
    /**
     * @var array Primary professions
     */
    public $professions;
    
    /**
     * @var array Other names/aliases
     */
    public $otherNames;
}
