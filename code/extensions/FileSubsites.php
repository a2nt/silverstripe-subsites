<?php

use SilverStripe\Forms\FieldList;
use SilverStripe\Assets\Folder;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Security\Permission;
use SilverStripe\ORM\DataExtension;

/**
 * Extension for the File object to add subsites support.
 */
class FileSubsites extends DataExtension
{
    // If this is set to true, all folders created will be default be
    // considered 'global', unless set otherwise
    public static $default_root_folders_global = false;

    private static $has_one = [
        'Subsite' => 'Subsite',
    ];

    /**
     * Amends the CMS tree title for folders in the Files & Images section.
     * Prefixes a '* ' to the folders that are accessible from all subsites.
     */
    public function alternateTreeTitle()
    {
        if ($this->owner->SubsiteID == 0) {
            return ' * '.$this->owner->Title;
        } else {
            return $this->owner->Title;
        }
    }

    /**
     * Add subsites-specific fields to the folder editor.
     */
    public function updateCMSFields(FieldList $fields)
    {
        if ($this->owner instanceof Folder) {
            $sites = Subsite::accessible_sites('CMS_ACCESS_AssetAdmin');
            $values = [];
            $values[0] = _t('FileSubsites.AllSitesDropdownOpt', 'All sites');
            foreach ($sites as $site) {
                $values[$site->ID] = $site->Title;
            }
            ksort($values);
            if ($sites) {
                //Dropdown needed to move folders between subsites
                $dropdown = DropdownField::create(
                    'SubsiteID',
                    _t('FileSubsites.SubsiteFieldLabel', 'Subsite'),
                    $values
                );
                $dropdown->addExtraClass('subsites-move-dropdown');
                $fields->push($dropdown);
                $fields->push(LiteralField::create(
                    'Message',
                    '<p class="message notice">'.
                    _t('ASSETADMIN.SUBSITENOTICE', 'Folders and files created in the main site are accessible by all subsites.')
                    .'</p>'
                ));
            }
        }
    }

    /**
     * Update any requests to limit the results to the current site.
     */
    public function augmentSQL(SilverStripe\ORM\Queries\SQLSelect $query, SilverStripe\ORM\DataQuery $dataQuery = null)
    {
        if (Subsite::$disable_subsite_filter) {
            return;
        }

        // If you're querying by ID, ignore the sub-site - this is a bit ugly... (but it was WAYYYYYYYYY worse)
        //@TODO I don't think excluding if SiteTree_ImageTracking is a good idea however because of the SS 3.0 api and ManyManyList::removeAll() changing the from table after this function is called there isn't much of a choice

        $from = $query->getFrom();
        if (isset($from['SiteTree_ImageTracking']) || $query->filtersOnID()) {
            return;
        }

        $subsiteID = (int) Subsite::currentSubsiteID();

        // The foreach is an ugly way of getting the first key :-)
        foreach ($query->getFrom() as $tableName => $info) {
            $where = "\"$tableName\".\"SubsiteID\" IN (0, $subsiteID)";
            $query->addWhere($where);
            break;
        }

        $sect = array_values($query->getSelect());
        $isCounting = strpos($sect[0], 'COUNT') !== false;

        // Ordering when deleting or counting doesn't apply
        if (!$isCounting) {
            $query->addOrderBy('"SubsiteID"');
        }
    }

    public function onBeforeWrite()
    {
        if (!$this->owner->ID && !$this->owner->SubsiteID) {
            if (self::$default_root_folders_global) {
                $this->owner->SubsiteID = 0;
            } else {
                $this->owner->SubsiteID = Subsite::currentSubsiteID();
            }
        }
    }

    public function onAfterUpload()
    {
        // If we have a parent, use it's subsite as our subsite
        if ($this->owner->Parent()) {
            $this->owner->SubsiteID = $this->owner->Parent()->SubsiteID;
        } else {
            $this->owner->SubsiteID = Subsite::currentSubsiteID();
        }
        $this->owner->write();
    }

    public function canEdit($member = null)
    {
        $session = Controller::curr()->getRequest()->getSession();
        // Check the CMS_ACCESS_SecurityAdmin privileges on the subsite that owns this group
        $subsiteID = $session->get('SubsiteID');
        if ($subsiteID && $subsiteID == $this->owner->SubsiteID) {
            return true;
        } else {
            $session->set('SubsiteID', $this->owner->SubsiteID);
            $access = Permission::check(['CMS_ACCESS_AssetAdmin', 'CMS_ACCESS_LeftAndMain']);
            $session->set('SubsiteID', $subsiteID);

            return $access;
        }
    }

    /**
     * Return a piece of text to keep DataObject cache keys appropriately specific.
     */
    public function cacheKeyComponent()
    {
        return 'subsite-'.Subsite::currentSubsiteID();
    }
}
