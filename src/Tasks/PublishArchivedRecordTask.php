<?php
/**
 * "Publish the archived record".
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author Catalyst IT <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://nzta.govt.nz
 **/
namespace NZTA\SDLT\Tasks;

use SilverStripe\Dev\BuildTask;
use NZTA\SDLT\Model\Questionnaire;
use NZTA\SDLT\Model\Task;
use NZTA\SDLT\Model\Question;
use NZTA\SDLT\Model\AnswerInputField;
use NZTA\SDLT\Model\AnswerActionField;
use SilverStripe\Versioned\Versioned;

class PublishArchivedRecordTask extends BuildTask {

    /**
     * Title of this task
     * @var string
     */
    public $title = 'Publish archived task.';

    /**
     * Segment of this task
     * @var string
     */
    private static $segment = 'PublishArchivedRecordTask';

    /**
     * Description of this task
     * @var string
     */
    public $description = 'Publish archived records.';

    /**
     * Default "run" method, required when implementing BuildTask
     *
     * @param HTTPRequest $request default parameter
     * @return void
     */
    public function run($request)
    {
        echo "Task is running......";
        echo "<br/>";
        echo "<br/>";

        $questionnaires = Questionnaire::get();
        echo "Checking questionnair start....";
        echo "<br/>";
        foreach ($questionnaires as $questionnaire) {
            if ($questionnaire->isArchived()) {
                echo $questionnaire->Name;
                echo "<br/>";
                $questionnaire->publish('Stage', 'Live');
            }
        }
        echo "Checking questionnair done....";
        echo "<br/>";
        echo "<br/>";

        echo "Checking task start....";
        echo "<br/>";
        $tasks = Task::get();
        foreach ($tasks as $task) {
            if ($task->isArchived()) {
                echo $task->Name;
                echo "<br/>";
                $task->publish('Stage', 'Live');
            }
        }
        echo "Checking task done....";
        echo "<br/>";
        echo "<br/>";

        echo "Checking question start....";
        echo "<br/>";
        $questions = Question::get();
        foreach ($questions as $question) {
            if ($question->isArchived()) {
                echo $question->Name;
                echo "<br/>";
                $question->publish('Stage', 'Live');
            }
        }
        echo "Checking question done....";
        echo "<br/>";
        echo "<br/>";

        echo "Checking answer input field start....";
        echo "<br/>";
        $inputFields = AnswerInputField::get();
        foreach ($inputFields as $inputField) {
            if ($inputField->isArchived()) {
                echo $inputField->Name;
                echo "<br/>";
                $inputField->publish('Stage', 'Live');
            }
        }
        echo "Checking answer input field done....";
        echo "<br/>";
        echo "<br/>";

        echo "Checking answer action field start....";
        echo "<br/>";
        $actionFields = AnswerActionField::get();
        foreach ($actionFields as $actionField) {
            if ($actionField->isArchived()) {
                echo $actionField->Name;
                echo "<br/>";
                $actionField->publish('Stage', 'Live');
            }
        }
        echo "Checking answer action field done....";
        echo "<br/>";

        echo "<br/>";
        echo "Task is completed successfully......";
    }
}
