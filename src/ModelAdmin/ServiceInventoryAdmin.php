<?php
/**
 * This file contains the "ServiceInventoryAdmin" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2021 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\ModelAdmin;

use NZTA\SDLT\Model\AccreditationMemo;
use NZTA\SDLT\Model\ServiceInventory;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\ORM\HasManyList;

/**
 * Class ServiceInventoryAdmin
 *
 */
class ServiceInventoryAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static $url_segment = 'service-inventory-admin';

    /**
     * @var string
     */
    private static $menu_title = 'Service Inventory';

    /**
     * @var string[]
     */
    private static $managed_models = [
        ServiceInventory::class,
        AccreditationMemo::class
    ];

    /**
     * @param int       $id     ID
     * @param FieldList $fields Fields
     * @return Form
     */
    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $gridFieldName = $this->sanitiseClassName($this->modelClass);

        /* @var GridField $gridField */
        $gridField = $form->Fields()->fieldByName($gridFieldName);
        $config = $gridField->getConfig();
        $config->removeComponent($config->getComponentByType(GridFieldPrintButton::class));
        $config->removeComponent($config->getComponentByType(GridFieldImportButton::class));
        $config->removeComponent($config->getComponentByType(GridFieldExportButton::class));
        $gridField->setConfig($config);

        return $form;
    }
}
