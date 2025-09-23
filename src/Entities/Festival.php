<?php

namespace Mfonte\ImdbScraper\Entities;

class Festival extends Entity
{
    /**
     * @var string Festival ID (if available)
     */
    public $id;

    /**
     * @var string Festival/Award show name
     */
    public $name;

    /**
     * @var Dataset List of awards from this festival
     */
    public $awards = null;

    /**
     * Custom setter for awards to ensure they're casted properly
     *
     * @param array|Dataset $awards
     */
    public function setAwards($awards): void
    {
        if ($awards instanceof Dataset) {
            $this->awards = $awards;
        } elseif (is_array($awards)) {
            $dataset = new Dataset;
            foreach ($awards as $key => $award) {
                if ($award instanceof Award) {
                    $dataset->put($key, $award);
                } else {
                    $dataset->put($key, Award::newFromArray($award));
                }
            }
            $this->awards = $dataset;
        } else {
            $this->awards = null;
        }
    }
}