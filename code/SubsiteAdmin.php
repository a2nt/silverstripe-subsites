<?php

use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Admin\ModelAdmin;

/**
 * Admin interface to manage and create {@link Subsite} instances.
 */
class SubsiteAdmin extends ModelAdmin
{
    private static $managed_models = ['Subsite'];

    private static $url_segment = 'subsites';

    private static $menu_title = 'Subsites';

    private static $menu_icon = 'subsites/images/subsites.png';

    public $showImportForm = false;

    private static $tree_class = 'Subsite';

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $grid = $form->Fields()->dataFieldByName('Subsite');
        if ($grid) {
            $grid->getConfig()->removeComponentsByType(GridFieldDetailForm::class);
            $grid->getConfig()->addComponent(GridFieldSubsiteDetailForm::create());
        }

        return $form;
    }
}
