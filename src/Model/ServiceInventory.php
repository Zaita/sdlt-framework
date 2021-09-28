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

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;

/**
 * This service record will be used apart of the ServiceInventory
 * ModelAdmin and Accreditation Memo
 */
class ServiceInventory extends DataObject implements PermissionProvider
{
    /**
     * @var string
     */
    private static $table_name = 'ServiceInventory';

    /**
     * @var array
     */
    private static $db = [
        'ServiceName' => 'Varchar(255)',
        'OperationalStatus' => 'Enum(array("live", "retired"))',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'BusinessOwner' => Member::class,
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'ServiceName',
        'BusinessOwner.Name' => 'Business Owner',
        'getPrettifyOperationalStatus' => 'Operational Status',
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
}
