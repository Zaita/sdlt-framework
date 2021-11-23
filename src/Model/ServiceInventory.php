<?php

/**
 * This file contains the "ServiceInventory" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2021 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Model;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\ORM\HasManyList;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ScaffoldingProvider;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
/**
 * This service record will be used apart of the ServiceInventory
 * ModelAdmin and Accreditation Memo
 */
class ServiceInventory extends DataObject implements PermissionProvider, ScaffoldingProvider
{
    /**
     * @var string
     */
    private static $table_name = 'ServiceInventory';

    /**
     * @var boolean
     */
    private static $show_overwrite_for_json_import = true;

    /**
     * @var array
     */
    private static $db = [
        'ServiceName' => 'Varchar(255)',
        'BusinessOwner' => 'Varchar(255)',
        'OperationalStatus' => 'Enum(array("live", "retired"))',
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'AccreditationMemos' => AccreditationMemo::class,
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'ServiceName',
        'BusinessOwner' => 'Business Owner',
        'getPrettifyOperationalStatus' => 'Operational Status',
        'ActiveServiceMemo' => 'Active Service Memos',
        'ActiveChangeMemo' => 'Active Change Memos',
        'IssueDate' => 'Issue Date',
        'ExpirationDate' => 'Expiration Date',
    ];

    /**
     * @var array
     */
    private static $searchable_fields = [
        'ServiceName',
        'BusinessOwner',
    ];

    /**
     * @return string
     */
    public function getPrettifyOperationalStatus()
    {
        $mapping = [
            'live' => 'Live',
            'retired' => 'Retired'
        ];

        return isset($mapping[$this->OperationalStatus])
            ? $mapping[$this->OperationalStatus]
            : $this->OperationalStatus;
    }

    /**
     * Active Service Memos is a count() query on the
     * number of "Service" Type Accreditation Memos
     *
     * @return integer
     */
    public function getActiveServiceMemo()
    {
        $accreditationMemos = $this->AccreditationMemos();

        if ($accreditationMemos && $accreditationMemos instanceof HasManyList) {
            $activeServiceMemos = $accreditationMemos
                ->filter([
                    'AccreditationStatus' => 'active',
                    'MemoType' => 'service'
                ])
                ->count();

            return $activeServiceMemos;
        }

        return 0;
    }

    /**
     * Active Change Memos is a count() query on the
     * number of "Change" Type Accreditation Memos
     *
     * @return integer
     */
    public function getActiveChangeMemo()
    {
        $accreditationMemos = $this->AccreditationMemos();

        if ($accreditationMemos && $accreditationMemos instanceof HasManyList) {
            $activeChangeMemos = $accreditationMemos
                ->filter([
                    'AccreditationStatus' => 'active',
                    'MemoType' => 'change'
                ])
                ->count();

                return $activeChangeMemos;
        }

        return 0;
    }

    /**
     * Issue Date is the created_at for the oldest
     * active "Service" Type accreditation memo
     *
     * @return string
     */
    public function getIssueDate()
    {
        $accreditationMemos = $this->AccreditationMemos();

        if ($accreditationMemos && $accreditationMemos instanceof HasManyList) {
            $accreditationMemo =  $accreditationMemos
                ->filter([
                     'AccreditationStatus' => 'active',
                     'MemoType' => 'service'
                ])
                ->sort('Created', 'ASC')
                ->first();

            if ($accreditationMemo && ($date = $accreditationMemo->Created)) {
                return date('d/m/Y', strtotime($date));
            }
        }

        return "";
    }

    /**
     * Expiration Date is the most recent (or furtherest into the future)
     * "ExpirationDate" for the "Service" Type accreditation memo
     *
     * @return string
     */
    public function getExpirationDate()
    {
        $accreditationMemos = $this->AccreditationMemos();

        if ($accreditationMemos && $accreditationMemos instanceof HasManyList) {
            $accreditationMemo =  $accreditationMemos
                ->filter([
                    'AccreditationStatus' => 'active',
                    'MemoType' => 'service'
                ])
                ->sort('ExpirationDate', 'DESC')
                ->first();

            if ($accreditationMemo && ($date = $accreditationMemo->ExpirationDate)) {
                return date('d/m/Y', strtotime($date));
            }
        }

        return "";
    }

    /**
     * Determines the AccreditationStatus displayed in detail view
     * @return string
     */
    public function getAccreditationStatus()
    {
        $accreditationMemos = $this->AccreditationMemos();

        if ($accreditationMemos && $accreditationMemos instanceof HasManyList) {
            $activeAccreditationStatus = $accreditationMemos
                ->filter([
                    'AccreditationStatus' => 'active',
                    'MemoType' => 'service',
                    'ExpirationDate:GreaterThanOrEqual' => date('Y-m-d')
                ])
                ->count();

            if ($activeAccreditationStatus > 0) {
                return "active";
            } else {
                return "expired";
            }
        }

        return "";
    }

    /**
     * Permission-provider to import and edit a Service
     *
     * @return array
     */
    public function providePermissions()
    {
        return [
            'IMPORT_SERVICE' => 'Allow user to import a Service inventory',
            'EDIT_SERVICE' => 'Allow user to edit a Service inventory',
        ];
    }

    /**
     * Allow logged-in user to access the model
     *
     * @param Member|null $member member
     * @return bool
     */
    public function canView($member = null)
    {
        return (Security::getCurrentUser() !== null);
    }

    /**
     * Only ADMIN users and user with edit permission should be able to edit Service
     *
     * @param Member $member to check the permission of
     * @return boolean
     */
    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        // checkMember(<Member>, [<at-least-one-match>])
        $canEdit = Permission::checkMember($member, [
            'ADMIN',
            'EDIT_SERVICE'
        ]);

        return $canEdit;
    }


    /**
     * Only ADMIN users and user with import permission should be able to import Service
     *
     * @param Member $member to check the permission of
     * @return boolean
     */
    public function canImport($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        // checkMember(<Member>, [<at-least-one-match>])
        $canImport = Permission::checkMember($member, [
            'ADMIN',
            'IMPORT_SERVICE'
        ]);

        return $canImport;
    }


    /**
     * @return ValidationResult
     */
    public function validate()
    {
        $result = parent::validate();

        // validation for unique service name
        $service = self::get()
           ->filter([
               'ServiceName' => $this->ServiceName
           ])->exclude('ID', $this->ID);

        if ($service->count()) {
            $result->addError(
                sprintf(
                    'Service name "%s" already exists. Please enter a unique Service name.',
                    $this->ServiceName
                )
            );
        }

        return $result;
    }

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeFieldFromTab('Root', 'AccreditationMemos');

        // hides AccreditationStatus and ExpirationDate
        // when creating new service inventory
        if (!empty($this->getAccreditationStatus())) {
            $fields->insertAfter(
                'BusinessOwner',
                ReadonlyField::create(
                    'AccreditationStatus',
                    'Accreditation status'
                )
            );

            $fields->insertAfter(
                'AccreditationStatus',
                ReadonlyField::create(
                    'ExpirationDate',
                    'Expiration date'
                )
            );
        }

        $config = GridFieldConfig_Base::create();
        $dataColumns = $config->getComponentByType(GridFieldDataColumns::class);
        $sortableHeader = $config->getComponentByType(GridFieldSortableHeader::class);
        $config->removeComponentsByType(GridFieldFilterHeader::class);

        $accreditationMemos = $this->AccreditationMemos();

        if ($accreditationMemos && $accreditationMemos instanceof HasManyList) {
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    $accreditationMemosGrid = new GridField(
                        'IssuedAccreditationMemo',
                        'Issued Accreditation Memos',
                        $this->AccreditationMemos(),
                        $config
                    )
                ],
            );
        }

        $dataColumns->setDisplayFields([
            'getPrettifyMemoType' => 'Type',
            'getPrettifyCreated'=> 'Issue Date',
            'getPrettifyExpirationDate' => 'Expiration Date',
            'getPrettifyAccreditationStatus' => 'Status',
            'IssuedBy' => 'Issued By',
            'CertifiedBy' => 'Certified By',
            'AccreditedBy' => 'Accredited By',
            'SummaryPageLink' => 'Link'
        ]);

        $sortableHeader->setFieldSorting([
            'getPrettifyMemoType'=> 'MemoType',
            'getPrettifyCreated'=> 'Created',
            'getPrettifyExpirationDate' => 'ExpirationDate',
            'getPrettifyAccreditationStatus' => 'AccreditationStatus',
        ]);

        return $fields;
    }

    /**
     * create service inventory from json import
     * @param object  $incomingJson service inventory json object
     * @param boolean $overwrite    overwrite the existing service inventory
     * @return void
     */
    public static function create_record_from_json($incomingJson, $overwrite = false)
    {
        $serviceInventoriesJson = $incomingJson->service;
        $serviceInventoryObj = '';

        foreach ($serviceInventoriesJson as $serviceInventoryJson) {
            $serviceInventoryObj = self::get_by_name($serviceInventoryJson->service_name);
            // if service is existing then update business owner
            if ($overwrite) {
                if (!empty($serviceInventoryObj)) {
                    $serviceInventoryObj->BusinessOwner = $serviceInventoryJson->business_owner;
                }
            }

            // if service obj doesn't exist with the same name then create a new object
            if (empty($serviceInventoryObj)) {
                $serviceInventoryObj = self::create();
                $serviceInventoryObj->ServiceName = $serviceInventoryJson->service_name;
                $serviceInventoryObj->BusinessOwner = $serviceInventoryJson->business_owner;

                // update value of OperationalStatus based on if it was included in json
                if (property_exists($serviceInventoryJson, "operational_status")) {
                    $serviceInventoryObj->OperationalStatus = $serviceInventoryJson->operational_status;
                } else {
                    $serviceInventoryObj->OperationalStatus = "live";
                }
            }

            $serviceInventoryObj->write();
        }
    }

    /**
     * get service inventory by name
     *
     * @param string $serviceName service inventory name
     * @return object|null
     */
    public static function get_by_name($serviceName)
    {
        $service = self::get()
            ->filter(['ServiceName' => $serviceName])
            ->first();

        return $service;
    }

    /**
     * @param SchemaScaffolder $scaffolder Scaffolder
     * @return SchemaScaffolder
     */
    public function provideGraphQLScaffolding(SchemaScaffolder $scaffolder)
    {
        // Provide entity type
        $scaffolder
            ->type(serviceinventory::class)
            ->addFields([
                'ID',
                'ServiceName',
            ])
            ->operation(SchemaScaffolder::READ)
            ->setName('readServiceInventory')
            ->setUsePagination(false);

        return $scaffolder;
    }

}
