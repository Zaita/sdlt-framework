<?php

/**
 * This file contains the "AuditEvent" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2019 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\Security\Security;
use NZTA\SDLT\Helper\ClassSpec;

/**
 * A discrete audit event.
 *
 * See {@link AuditService} and {@link AuditAdmin}.
 */
class AuditEvent extends DataObject
{
    /**
     * @var array
     */
    private static $db = [
        'Event' => DBVarchar::class,
        'Model' => DBVarchar::class,
        'Extra' => DBText::Class,
        'UserData'  => DBVarchar::class,
        'HistoryLink' => DBVarchar::class,
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Event',
        'Model' => 'Type',
        'UserData'  => 'User Data',
        'Created' => 'Audit Date',
        'Extra' => 'Extra Data',
    ];

    /**
     * @var string
     */
    private static $table_name = 'AuditEvent';

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab('Root.Main', ReadonlyField::create('Created', 'Logged Date'));
        $fields->removeByName(['HistoryLink']);
        if ($this->HistoryLink != 'N/A') {
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    HTMLReadonlyField::create(
                        $this->historyDescription(),
                        'History Link',
                        $this->historyDescription()
                    )
                ]
            );
        }

        return $fields;
    }

    /**
     * Internal method to commit a single audit event.
     *
     * @param  string     $event     An a single event, declared as a service constant.
     * @param  string     $extra     Additional data to save alongside the event-name itself.
     * @param  DataObject $model     The model that invoked this commit.
     * @param  string     $userData  Info about the user that fired the event.
     * @return AuditEvent
     */
    public function log(string $event, string $extra, DataObject $model, $userData = '', $historyLink = '') : AuditEvent
    {
        $this->setField('Event', $event);
        $this->setField('Extra', $extra);
        $this->setField('Model', ClassSpec::short_name(get_class($model)));
        $this->setField('UserData', $userData ?: 'N/A');
        $this->setField('HistoryLink', $historyLink ?: 'N/A');

        return $this;
    }

    /**
     * @param  mixed Member|null $member Current member
     * @return boolean
     */
    public function canEdit($member = null)
    {
        return false;
    }

    /**
     * @return string
     */
    public function historyDescription()
    {
        return sprintf('<p>Please click on the <a href="%s"> Link </a>'
                        . 'to check the record</p>',
                        $this->HistoryLink
        );
    }

    /**
     * @param  mixed Member|null $member Current member
     * @return boolean
     */
    public function canDelete($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        return $member->getIsAdmin();
    }
}
