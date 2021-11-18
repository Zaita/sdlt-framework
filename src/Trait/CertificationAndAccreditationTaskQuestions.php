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

use NZTA\SDLT\Model\Question;
use NZTA\SDLT\Model\AnswerInputField;

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
    }

}
