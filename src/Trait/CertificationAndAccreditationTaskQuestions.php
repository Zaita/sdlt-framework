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
    public function addQuestionsForCertificationAndAccreditationTask()
    {
        if (!$this->Questions()->count()) {
            $this->questionOne();
            $this->questionTwo();
            $this->questionThree();
            $this->questionFour();
            $this->questionFive();
            $this->questionSix();
            $this->questionSeven();
        }
    }

    /**
     * @return void
     */
    public function questionOne()
    {
        // question one
        $questionID = $this->addQuestion(
            "Description",
            "Description of the change or project",
            "Please provide a short description of the change or
            project that can be used in the accreditation memo.",
            "input"
        );

        if ($questionID) {
            $inputFieldID = $this->addInputField(
                "",
                "rich text editor",
                false,
                $questionID,
                'product description'
            );
        }
    }

    /**
     * @return void
     */
    public function questionTwo()
    {
        // question two
        $questionID = $this->addQuestion(
            "Service name",
            "Service name",
            "Please specify the name of the service. If you can't find the service,
            please add it to the service inventory in the administration panel.",
            "input"
        );

        if ($questionID) {
            $inputFieldID = $this->addInputField(
                "",
                "service register",
                false,
                $questionID,
                'service name'
            );
        }
    }

    /**
     * @return void
     */
    public function questionThree()
    {
        // question three
        $questionID = $this->addQuestion(
            "Information classification",
            "Information classification",
            "Please select the Information classification for this change or project.
            The following value has been populated from the 'Information Classification' task if one exists,
            please override this if there is a legitimate reason to modify the classification.",
            "input"
        );

        // add dropdown field
        if ($questionID) {
            $inputFieldID = $this->addInputField(
                "Classification level",
                "information classification",
                true,
                $questionID,
                'classification level'
            );

            // add dropdown value
            if ($inputFieldID) {
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
                    $selectionFieldID = $this->addselectionField(
                        $result,
                        strtolower($result),
                        $inputFieldID
                    );
                }
            }
        }
    }

    /**
     * @return void
     */
    public function questionFour()
    {
        //question four
        $questionID = $this->addQuestion(
            "Risk Profile",
            "Risk Profile",
            "The following is a pre-populated table with the
                outcome of the security risk assessment task(s).",
            "display"
        );
    }

    /**
     * @return void
     */
    public function questionFive()
    {
        //question five
        $questionID = $this->addQuestion(
            "Accreditation scope",
            "Accreditation scope",
            "Please select and describe the level of Accreditation to be issued.",
            "input"
        );

        if ($questionID) {
            // add accreditation level field
            $inputFieldID = $this->addInputField(
                "Accreditation level",
                "multiple-choice: single selection",
                false,
                $questionID,
                "accreditation level",
                "Service"
            );

            if ($inputFieldID) {
                $selectionFieldID = $this->addselectionField(
                    "Service level",
                    "Service",
                    $inputFieldID
                );

                $selectionFieldID = $this->addselectionField(
                    "Change level",
                    "Change",
                    $inputFieldID
                );
            }

            // add description field
            $inputFieldID = $this->addInputField(
                "Description",
                "rich text editor",
                false,
                $questionID,
                "accreditation description"
            );
        }
    }

    /**
     * @return void
     */
    public function questionSix()
    {
        // question six
        $questionID = $this->addQuestion(
            "Type of accreditation",
            "Type of accreditation",
            "Please select the type of accreditation you wish to recommend the
            issuance of. A full accreditation should have minimal renewal recommendations
            and a longer period. An interim accreditation should be a short-term one to
            allow remediation of serious renewal recommendations.",
            "input"
        );

        if ($questionID) {
            // add type field
            $inputFieldID = $this->addInputField(
                "Type",
                "dropdown",
                false,
                $questionID,
                "accreditation type"
            );

            foreach (['Full accreditation', 'Interim accreditation'] as $type) {
                $selectionFieldID = $this->addselectionField(
                    $type,
                    strtolower($type),
                    $inputFieldID
                );
            }

            // add period field
            $inputFieldID = $this->addInputField(
                "Period",
                "dropdown",
                false,
                $questionID,
                "accreditation period"
            );

            $periods = [
                '1 month',
                '3 months',
                '6 months',
                '9 months',
                '12 months',
                '18 months',
                '24 months'
            ];

            foreach ($periods as $period) {
                $selectionFieldID = $this->addselectionField(
                    $period,
                    $period,
                    $inputFieldID
                );
            }

            // add rich text Editor for Accreditation renewal recommendations
            $inputFieldID = $this->addInputField(
                "Accreditation renewal recommendations",
                "rich text editor",
                false,
                $questionID,
                "accreditation renewal recommendations"
            );
        }
    }

    /**
     * @return void
     */
    public function questionSeven()
    {
        //question seven
        $questionID = $this->addQuestion(
            "Review",
            "Review",
            "Please review the accreditation memo below and ensure everything
            has been completed appropriately.",
            "display"
        );
    }

    /**
     * @param string $title
     * @param string $heading
     * @param string $description
     * @param string $answerFieldType
     *
     * @return integer
     */
    public function addQuestion($title, $heading, $description, $answerFieldType)
    {
        $question = QUESTION::create();

        $question->Title = $title;
        $question->QuestionHeading = $heading;
        $question->Description = $description;
        $question->AnswerFieldType = $answerFieldType;
        $question->TaskID = $this->ID;
        $question->write();

        return $question->ID;
    }

    /**
     * @param string  $label
     * @param string  $inputType
     * @param boolean $required
     * @param integer $questionID
     * @param string  $defaultValue
     *
     * @return integer
     */
    public function addInputField($label, $inputType, $required, $questionID, $certificationAndAccreditationInputType, $defaultValue='')
    {
        $inputField = AnswerInputField::create();

        $inputField->Label = $label;
        $inputField->InputType = $inputType;
        $inputField->Required = $required;
        $inputField->QuestionID = $questionID;
        $inputField->CertificationAndAccreditationInputType = $certificationAndAccreditationInputType;

        if (!empty($defaultValue)) {
            $inputField->MultiChoiceSingleAnswerDefault = $defaultValue;
        }

        $inputField->write();

        return $inputField->ID;
    }

    /**
     * @param string  $label
     * @param string  $value
     * @param integer $inputFieldID
     *
     * @return integer
     */
    public function addselectionField($label, $value, $inputFieldID)
    {
        $selectionField =  MultiChoiceAnswerSelection::create();

        $selectionField->Label = $label;
        $selectionField->Value = $value;
        $selectionField->AnswerInputFieldID = $inputFieldID;

        $selectionField->write();

        return $selectionField->ID;
    }
}
