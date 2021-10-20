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
use SilverStripe\ORM\HasManyList;

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
    private static $has_many = [
        'AccreditationMemos' => AccreditationMemo::class,
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'ServiceName',
        'BusinessOwner.Name' => 'Business Owner',
        'getPrettifyOperationalStatus' => 'Operational Status',
        'ActiveServiceMemo' => 'Active Service Memos',
        'ActiveChangeMemo' => 'Active Change Memos',
        'IssueDate' => 'Issue Date',
        'ExpirationDate' => 'Expiration Date',
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
