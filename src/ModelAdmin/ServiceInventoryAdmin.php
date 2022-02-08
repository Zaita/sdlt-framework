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

use NZTA\SDLT\Form\GridField\GridFieldImportJsonButton;
use NZTA\SDLT\Model\AccreditationMemo;
use NZTA\SDLT\Model\ServiceInventory;
use NZTA\SDLT\Traits\SDLTAdminCommon;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;

/**
 * Class ServiceInventoryAdmin
 *
 */
class ServiceInventoryAdmin extends ModelAdmin
{
    use SDLTAdminCommon;

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
        ServiceInventory::class
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
        $config->removeComponent($config->getComponentByType(GridFieldExportButton::class));

        if (!$this->modelClass::config()->get('show_import_button')) {
            $config->removeComponent($config->getComponentByType(GridFieldImportButton::class));
        }

        $sortableHeader = $config->getComponentByType(GridFieldSortableHeader::class);
        $sortableHeader->setFieldSorting([
            'getPrettifyOperationalStatus' => 'OperationalStatus',
            'IssueDate' => 'Created',
        ]);

        // show json import button only for the model has "canImport" method
        // and user has permission to import (set in CMS with user group permission)
        if (singleton($this->modelClass)->hasMethod('canImport') &&
            singleton($this->modelClass)->canImport()) {
            $config->addComponent(
                GridFieldImportJsonButton::create('buttons-before-left')
                ->setImportJsonForm($this->ImportJsonForm())
                ->setModalTitle('Import from Json')
            );
        }

        $gridField->setConfig($config);

        return $form;
    }

    /**
     *
     * @return \SilverStripe\ORM\Datalist
     */
    public function getList()
    {
        $list = parent::getList();

        // access all the search parameters
        $searchParams = $this->getRequest()->requestVar('filter');

        if (isset($searchParams)) {
            // get the input in the DefaultSearch hidden field
            $defaultSearch = array_column($searchParams, 'DefaultSearch');

            // apply search filters to list if input is in the searchbox
            if ($defaultSearch) {
                $searchResults = $list->filterAny([
                    'ServiceName:PartialMatch' => $defaultSearch,
                    'BusinessOwner:PartialMatch' => $defaultSearch,
                ]);

                return $searchResults;
            }
        }

        return $list;
    }
}
