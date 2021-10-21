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
        'getPrettifyMemoType' => 'Memo Type',
        'getPrettifyAccreditationStatus' => 'Accreditation Status',
        'getPrettifyCreated' => 'Creation Date',
        'getPrettifyExpirationDate' => 'Expiration Date',
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

        if ($this->ExpirationDate) {
            $status = $this->getAccreditationStatusforDisplay($this->ExpirationDate);

            return isset($mapping[$status])
                ? $mapping[$status] : $this->AccreditationStatus;
        }

        return "";
    }

    /**
     * @return string
     */
    public function getPrettifyMemoType()
    {
        $mapping = [
            'service' => 'Service',
            'change' => 'Change'
        ];

        return isset($mapping[$this->MemoType])
            ? $mapping[$this->MemoType]
            : $this->MemoType;
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
     * @return mixed
     */
    public function getPrettifyExpirationDate()
    {
        return $this->dbObject('ExpirationDate')->format('dd/MM/y');
    }

    /**
     * @return mixed
     */
    public function getPrettifyCreated()
    {
        return $this->dbObject('Created')->format('dd/MM/y');
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

        if ($this->ExpirationDate) {
            $status = $this->getAccreditationStatusforDisplay($this->ExpirationDate);
            $fields
                ->dataFieldByName('AccreditationStatus')
                ->setValue($status);
        }

        return $fields;
    }

    /**
     * @return string
     */
    public function getAccreditationStatusforDisplay($date)
    {
        if ($this->ExpirationDate >= date('Y-m-d')) {
            return $this->AccreditationStatus = "active";
        } else {
            return $this->AccreditationStatus = "expired";
        }
    }
}
