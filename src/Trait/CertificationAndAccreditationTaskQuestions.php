<?php

/**
 * This file contains the "CertificationAndAccreditationTaskQuestions" trait.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2019 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Traits;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Security\Security;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\FormAction;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\CheckboxField;
use NZTA\SDLT\Model\AnswerInputField;
use NZTA\SDLT\Model\Question;
use NZTA\SDLT\Model\MultiChoiceAnswerSelection;

trait CertificationAndAccreditationTaskQuestions
{
    /**
     * @return void
     */
    public function questionOne()
    {
        // question one
        $question = QUESTION::create();

        $question->Title = "Description";
        $question->QuestionHeading = "Description of the change or project";
        $question->Description = "Please provide a short description of the change or
            project that can be used in the accreditation memo.";
        $question->AnswerFieldType = 'input';
        $question->TaskID = $this->ID;
        $question->write();

        if ($question->ID) {
            $inputField = AnswerInputField::create();
            $inputField->InputType = "rich text editor";
            $inputField->QuestionID = $question->ID;
            $inputField->write();
        }
    }

    /**
     * @return void
     */
    public function questionTwo()
    {
        // question two
        $question = QUESTION::create();

        $question->Title = "Service name";
        $question->QuestionHeading = "Service name";
        $question->Description = "Please specify the name of the service. If you can't find the service, please add it to the service inventory in the administration panel.";
        $question->AnswerFieldType = 'input';
        $question->TaskID = $this->ID;
        $question->write();

        if ($question->ID) {
            $inputField = AnswerInputField::create();
            $inputField->InputType = "service register";
            $inputField->QuestionID = $question->ID;
            $inputField->write();
        }
    }

    /**
     * @return void
     */
    public function questionThree()
    {
        // question three
        $question = QUESTION::create();

        $question->Title = "Information classification";
        $question->QuestionHeading = "Information classification";
        $question->Description = "Please select the Information classification for this change or project.
            The following value has been populated from the 'Information Classification' task if one exists,
            please override this if there is a legitimate reason to modify the classification.";
        $question->AnswerFieldType = 'input';
        $question->TaskID = $this->ID;
        $question->write();

        // add dropdown field
        if ($question->ID) {
            $inputField = AnswerInputField::create();
            $inputField->InputType = "information classification";
            $inputField->QuestionID = $question->ID;
            $inputField->Required = true;
            $inputField->write();

            // add dropdown value
            if ($inputField->ID) {
                $resultArray = [
                    'Unclassified',
                    'In-Confidence',
                    'Sensitive',
                    'Restricted',
                    'Confidential',
                    'Secret',
                    'Top-Secret'
                ];

                foreach ($resultArray as $result) {
                    $optionObj = MultiChoiceAnswerSelection::create();
                    $optionObj->Label = $result;
                    $optionObj->Value = strtolower($result);
                    $optionObj->AnswerInputFieldID = $inputField->ID;
                    $optionObj->write();
                }
            }
        }
    }

    /**
     * @return void
     */
    public function questionFour()
    {
        $question = QUESTION::create();

        $question->Title = "Risk Profile";
        $question->QuestionHeading = "Risk Profile";
        $question->Description = "The following is a pre-populated table with the
        outcome of the security risk assessment task(s).";
        $question->TaskID = $this->ID;
        $question->AnswerFieldType = 'display';
        $question->write();
    }
}
