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
        'IssueDate' => 'Date',
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
        'QuestionnaireSubmission' => QuestionnaireSubmission::class,
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
     * use questionnaire relationship to get summary page link
     *
     * @return string
     */
    public function getSummaryPageLink()
    {
        if ($this->QuestionnaireSubmission()) {
            return $this->QuestionnaireSubmission()->getSummaryPageLink();
        }

        return '';
    }

    /**
     * use questionnaire relationship CertificationAuthorityApprover
     *
     * @return string
     */
    public function getCertifiedBy()
    {
        if (!$this->QuestionnaireSubmission()) {
            return '';
        }

        $member = $this->QuestionnaireSubmission()->CertificationAuthorityApprover();

        if (!$member) {
            return '';
        }

        return trim($member->FirstName . ' ' . $member->Surname);
    }

    /**
     * use questionnaire relationship AccreditationAuthorityApprover
     *
     * @return string
     */
    public function getAccreditedBy()
    {
        if (!$this->QuestionnaireSubmission()) {
            return '';
        }

        $member = $this->QuestionnaireSubmission()->AccreditationAuthorityApprover();

        if (!$member) {
            return '';
        }

        return trim($member->FirstName . ' ' . $member->Surname);
    }

    /**
     * get the member who completed the c&a memo task
     *
     * @return string
     */
    public function getIssuedBy()
    {
        if (!$this->QuestionnaireSubmission()) {
            return '';
        }

        $caMemoTaskSubmission = $this->QuestionnaireSubmission()->TaskSubmissions()
            ->filter([
                'Task.TaskType' => 'certification and accreditation',
                'Status' => TaskSubmission::STATUS_COMPLETE
            ])->first();

        if (!$caMemoTaskSubmission) {
            return '';
        }

        $member = $caMemoTaskSubmission->completedBy();

        if (!$member) {
            return '';
        }

        return trim($member->FirstName . ' ' . $member->Surname);
    }

    /**
     * @return string
     */
    public function getPrettifyExpirationDate()
    {
        return date("d/m/Y", strtotime($this->ExpirationDate));
    }

    /**
     * @return string
     */
    public function getPrettifyIssueDate()
    {
        if ($this->IssueDate) {
            return date("d/m/Y", strtotime($this->IssueDate));
        }

        return date("d/m/Y", strtotime($this->Created));
    }

    /**
     * CMS Fields
     *
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
