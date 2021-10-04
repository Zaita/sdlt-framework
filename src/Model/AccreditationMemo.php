<?php

/**
 * This file contains the "AccreditationMemo" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2021 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Model;

use NZTA\SDLT\Model\ServiceInventory;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\DropdownField;

/**
 * Class AccreditationMemo
 */
class AccreditationMemo extends DataObject
{
    /**
     * @var string
     */
    private static $table_name = 'AccreditationMemo';

    /**
     * @var array
     */
    private static $db = [
        'MemoType' => 'Enum(array("service", "change"))',
        'AccreditationStatus' => 'Enum(array("active", "expired"), "expired")',
        'ExpirationDate' => 'Date',
    ];

    /**
     * @var array
     */
    private static $defaults = [
        "AccreditationStatus" => "expired"
    ];

    /**
     *
     * @var array
     */
    private static $has_one = [
        'Service' => ServiceInventory::class,
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Service.ServiceName' => 'Service Name',
        'getPrettifyAccreditationStatus' => 'Accreditation Status',
        'Created' => 'Creation Date',
        'ExpirationDate' => 'Expiration Date',
        'SummaryPageLink' => 'Submission Summary Link',
    ];

    /**
     * @return string
     */
    public function getPrettifyAccreditationStatus()
    {
        $mapping = [
            'active' => 'Active',
            'expired' => 'Expired'
        ];

        return isset($mapping[$this->AccreditationStatus])
            ? $mapping[$this->AccreditationStatus]
            : $this->AccreditationStatus;
    }

    /**
     * TODO: use questionnaire relationship to get summary page link
     * @return string
     */
    public function getSummaryPageLink()
    {
       return "";
    }

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['ServiceID']);
        $fields->addFieldsToTab(
            'Root.Main',
            [
                DropdownField::create(
                    'ServiceID',
                    'Service name',
                    ServiceInventory::get()->map('ID', 'ServiceName')
                )->setEmptyString(' ')
            ]);

        return $fields;
    }
}
