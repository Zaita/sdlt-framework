<?php

/**
 * This file contains the "QuestionnaireSubmissionAdmin" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2018 <silverstripedev@catalyst.net.nz>
 * @copyright 2018 Catalyst.Net Ltd
 * @license https://www.catalyst.net.nz (Commercial)
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\ModelAdmin;

use NZTA\SDLT\Model\QuestionnaireSubmission;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldViewButton;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\Tab;

/**
 * Class QuestionnaireSubmissionAdmin
 *
 * This class is used to manage Questionnaires sumission
 */
class QuestionnaireSubmissionAdmin extends ModelAdmin
{
    /**
     * @var string[]
     */
    private static $managed_models = [
        QuestionnaireSubmission::class,
    ];

    /**
     * @var string
     */
    private static $url_segment = 'questionnaire-submission-admin';

    /**
     * @var string
     */
    private static $menu_title = 'Questionnaires Submission';

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
        $currentGridField = $form->Fields()->fieldByName($gridFieldName);

        $list = $currentGridField->getList();

        $currentList = $list->exclude('QuestionnaireStatus', 'expired');

        $currentGridField->setList($currentList);

        $config = GridFieldConfig_RelationEditor::create();

        $config->removeComponentsByType(GridFieldAddNewButton::class);
        $config->removeComponentsByType(GridFieldDeleteAction::class);
        $config->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
        $config->getComponentByType(GridFieldDetailForm::class)
            ->setItemRequestClass(QuestionnaireSubmissionDetailForm_ItemRequest::class);

        // Remove default and add our own filter header with extension points
        // to retain API until deprecation in 5.0
        $config->removeComponentsByType(GridFieldFilterHeader::class);
        $config->addComponent(new GridFieldFilterHeader(
            false,
            function ($context) {
                $this->extend('updateSearchContext', $context);
            }
        ));

        $currentGridField->setConfig($config);

        $expiredSubmission = $list->filter(
            'QuestionnaireStatus',
            QuestionnaireSubmission::STATUS_EXPIRED
        );

        $expiredGridField = new GridField(
            'ExpiredSubmissions',
            '',
            $expiredSubmission,
            $config
        );

        $fields = new FieldList(
            $root = new TabSet(
                'Root',
                new Tab(
                    'CurrentSubmissions',
                    'Current' . '(' . count($currentList->exclude('QuestionnaireStatus', 'expired')) . ')',
                    $currentGridField
                ),
                new Tab(
                    'ExpiredSubmissions',
                    'Expired' . '(' . count($expiredSubmission) . ')',
                    $expiredGridField
                )
            )
        );

        $actions = new FieldList();

        $form = new Form(
            $this,
            'EditForm',
            $fields,
            $actions
        );

        $this->extend('updateEditForm', $form);

        return $form;
    }

    /**
     *
     * @return \SilverStripe\ORM\Datalist
     */
    public function getList()
    {
        $list = parent::getList();

        //access all the search parameters
        $searchParams = $this->getRequest()->requestVar('filter');

        if(isset($searchParams)){
            //get the value set in the DefaultSearch hidden field
            $defaultSearch = array_column($searchParams, 'DefaultSearch');

            //apply search filters to list if input is in the searchbox
            if(($defaultSearch)){
                $searchResults = $list->filterAny([
                    'ProductName:PartialMatch' => $defaultSearch[0],
                    'SubmitterName:PartialMatch' => $defaultSearch[0],
                    'SubmitterEmail:PartialMatch' => $defaultSearch[0]
                ]);

                return $searchResults;
            }
        }

        return $list;
    }
}
