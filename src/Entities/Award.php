<?php

namespace Mfonte\ImdbScraper\Entities;

class Award extends Entity
{
    /**
     * @var string The award category
     */
    public $category;

    /**
     * @var string The year of the award
     */
    public $year;

    /**
     * @var string The outcome (e.g., "Winner", "Nominee")
     */
    public $outcome;

    /**
     * @var array List of recipients
     */
    public $recipients = [];
}