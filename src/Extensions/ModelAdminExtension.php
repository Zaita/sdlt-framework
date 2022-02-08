<?php
/**
 * This file contains the "ModelAdminExtension" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2021 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Extension;

use NZTA\SDLT\Model\QuestionnaireSubmission;
use NZTA\SDLT\Model\ServiceInventory;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataExtension;

/**
 * Class ModelAdminExtension
 *
 */
class ModelAdminExtension extends DataExtension
{
    /**
     * @param mixed $context The current search context
     *
     * @return mixed $context
     */
    public function updateSearchContext($context)
    {
        // store search input from GridFieldFilterHeader search box into a hidden field
        if ($this->owner->modelClass == QuestionnaireSubmission::class ||
            $this->owner->modelClass == ServiceInventory::class) {
            $context->getFields()->insertBefore(HiddenField::create('DefaultSearch', 'Default Search'), '');
        }

        return $context;
    }
}
