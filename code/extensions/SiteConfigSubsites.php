<?php

use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataExtension;

/**
 * Extension for the SiteConfig object to add subsites support.
 */
class SiteConfigSubsites extends DataExtension
{
    private static $has_one = [
        'Subsite' => 'Subsite', // The subsite that this page belongs to
    ];

    /**
     * Update any requests to limit the results to the current site.
     */
    public function augmentSQL(SilverStripe\ORM\Queries\SQLSelect $query, SilverStripe\ORM\DataQuery $dataQuery = null)
    {
        if (Subsite::$disable_subsite_filter) {
            return;
        }

        // If you're querying by ID, ignore the sub-site - this is a bit ugly...
        if ($query->filtersOnID()) {
            return;
        }
        $regexp = '/^(.*\.)?("|`)?SubsiteID("|`)?\s?=/';
        foreach ($query->getWhereParameterised($parameters) as $predicate) {
            if (preg_match($regexp, $predicate)) {
                return;
            }
        }

        /*if($context = DataObject::context_obj()) $subsiteID = (int)$context->SubsiteID;
        else */$subsiteID = (int) Subsite::currentSubsiteID();

        $froms = $query->getFrom();
        $froms = array_keys($froms);
        $tableName = array_shift($froms);
        if ($tableName != SiteConfig::class) {
            return;
        }
        $query->addWhere("\"$tableName\".\"SubsiteID\" IN ($subsiteID)");
    }

    public function onBeforeWrite()
    {
        if ((!is_numeric($this->owner->ID) || !$this->owner->ID) && !$this->owner->SubsiteID) {
            $this->owner->SubsiteID = Subsite::currentSubsiteID();
        }
    }

    /**
     * Return a piece of text to keep DataObject cache keys appropriately specific.
     */
    public function cacheKeyComponent()
    {
        return 'subsite-'.Subsite::currentSubsiteID();
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->push(HiddenField::create('SubsiteID', 'SubsiteID', Subsite::currentSubsiteID()));
    }
}
