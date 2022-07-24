<?php

/**
 * This file contains the "TaskSubmission" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2018 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Model;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use NZTA\SDLT\GraphQL\GraphQLAuthFailure;
use Ramsey\Uuid\Uuid;
use SilverStripe\Forms\FieldList;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ResolverInterface;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ScaffoldingProvider;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\DataObjectScaffolder;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use NZTA\SDLT\Validation\QuestionnaireValidation;
use SilverStripe\Core\Convert;
use SilverStripe\Control\Director;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use NZTA\SDLT\Job\SendTaskSubmissionEmailJob;
use NZTA\SDLT\Job\SendTaskApprovalLinkEmailJob;
use NZTA\SDLT\Job\SendTaskStakeholdersEmailJob;
use NZTA\SDLT\Job\SendAllTheTasksCompletedEmailJob;
use SilverStripe\Forms\TextField;
use NZTA\SDLT\Model\JiraTicket;
use SilverStripe\Security\Group;
use NZTA\SDLT\Traits\SDLTRiskSubmission;
use NZTA\SDLT\Helper\SecurityRiskAssessmentCalculator;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use NZTA\SDLT\Extension\GroupExtension;

/**
 * Class TaskSubmission
 *
 * @property string QuestionnaireData
 * @property string AnswerData
 * @property string Status
 * @property string UUID
 * @property string Result
 * @property string SecureToken
 * @property int SubmitterID
 * @property int TaskID
 * @property int QuestionnaireSubmissionID
 * @property boolean LockAnswersWhenComplete
 * @property string SubmitterIPAddress
 * @property string CompletedAt
 * @property string JiraKey
 *
 * @method Member Submitter()
 * @method Task Task()
 * @method QuestionnaireSubmission QuestionnaireSubmission()
 * @method HasManyList SelectedComponents()
 * @method HasManyList JiraTickets()
 */
class TaskSubmission extends DataObject implements ScaffoldingProvider
{
    use SDLTRiskSubmission;

    const STATUS_START = 'start';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETE = 'complete';
    const STATUS_INVALID = 'invalid';
    const STATUS_APPROVED = 'approved';
    const STATUS_DENIED = 'denied';
    const STATUS_WAITING_FOR_APPROVAL = 'waiting_for_approval';
    const STATUS_EXPIRED = 'expired';

    /**
     * @var string
     */
    private static $table_name = 'TaskSubmission';

    /**
     * @var string
     */
    private $cvaTaskDataSource;

    /**
     * @var string
     */
    private $securityRiskAssessmentData = '';

    /**
     * @var bool
     */
    private $CanUpdateTask = false;

    /**
     * @var string
     */
    private $SelectedControls = '';

    /**
     * @var array
     */
    private static $db = [
        'QuestionnaireData' => 'Text', // store in JSON format
        'AnswerData' => 'Text', // store in JSON format
        'Status' => 'Enum(array("start", "in_progress", "complete", "waiting_for_approval", "approved", "denied", "invalid", "expired"))',
        'UUID' => 'Varchar(255)',
        'Result' => 'Varchar(255)',
        'SecureToken' => 'Varchar(64)',
        'LockAnswersWhenComplete' => 'Boolean',
        'CreateOnceInstancePerComponent' => 'Boolean',
        'SubmitterIPAddress' => 'Varchar(255)',
        'CompletedAt' => 'Datetime',
        'EmailRelativeLinkToTask' => 'Varchar(255)',
        'JiraKey' => 'Varchar(255)',
        'IsApprovalRequired' => 'Boolean',
        'IsTaskApprovalLinkSent' => 'Boolean',
        'IsStakeholdersEmailSent' => 'Boolean',
        'RiskResultData' => 'Text',
        'TaskRecommendationData' => 'Text',
        'CVATaskData' => 'Text',
        'UpdatedAccreditationPeriod' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Submitter' => Member::class,
        'completedBy' => Member::class,
        'TaskApprover' => Member::class,
        'Task' => Task::class,
        'QuestionnaireSubmission' => QuestionnaireSubmission::class,
        'ApprovalGroup' => Group::class
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'JiraTickets' => JiraTicket::class,
        'SelectedComponents' => SelectedComponent::class,
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'ID',
        'UUID',
        'Task.Name' => 'Task Name',
        'Status',
        'Result',
        'Created' => 'Created date',
        'CompletedAt' => 'Completed Date'
    ];

    /**
     * Default sort ordering
     * @var array
     */
    private static $default_sort = ['ID' => 'DESC'];

    /**
     * Allow logged-in user to access the model
     *
     * @param Member|null $member member
     * @return bool
     */
    public function canView($member = null)
    {
        return (Security::getCurrentUser() !== null);
    }

    /**
     * @return string
     */
    public function getProductAspects()
    {
        return $this->QuestionnaireSubmission()->getProductAspects();
    }

    /**
     * Don't allow to delete records
     *
     * @param Member|null $member member
     * @return bool
     */
    public function canDelete($member = null)
    {
        return false;
    }

    /**
     * @return string
     */
    public function getTaskName()
    {
        $task = $this->Task();
        if (!$task->exists()) {
            return "";
        }
        return $task->Name;
    }

    /**
     * @return bool
     */
    public function getCanUpdateTask()
    {
        return $this->CanUpdateTask;
    }

    /**
     * @return bool
     */
    public function setCanUpdateTask($canEdit)
    {
        return $this->CanUpdateTask = $canEdit;
    }

    /**
     * @return string
     */
    public function getTaskType()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->TaskType;
    }

    /**
     * @return string
     */
    public function getComponentTarget()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->ComponentTarget;
    }

    /**
     * @return string
     */
    public function getCVATaskDataSource() : string
    {
        if (!$this->cvaTaskDataSource) {
            $this->setCVATaskDataSource();
        }

        return $this->cvaTaskDataSource;
    }

    /**
     * @param string $dataSource jira/local/default
     * @return string
     */
    public function setCVATaskDataSource($dataSource = 'DefaultComponent')
    {
        $this->cvaTaskDataSource = $dataSource;
    }

    /**
     * Get Security Risk Assessment Data
     *
     * @return string
     */
    public function getSecurityRiskAssessmentData()
    {
        return $this->securityRiskAssessmentData;
    }

    /**
     * Get Security Risk Assessment Data
     *
     * @return string
     */
    public function setSecurityRiskAssessmentData($sraData)
    {
        $this->securityRiskAssessmentData = $sraData;
    }

    /**
     * @return string
     */
    public function getSraTaskHelpText()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->SraTaskHelpText;
    }

    /**
     * @return string
     */
    public function getSraTaskRecommendedControlHelpText()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->SraTaskRecommendedControlHelpText;
    }


    /**
     * @return string
     */
    public function getSraTaskRiskRatingHelpText()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->SraTaskRiskRatingHelpText;
    }

    /**
     * @return string
     */
    public function getSraTaskNotApplicableInformationText()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->SraTaskNotApplicableInformationText;
    }

    /**
     * @return string
     */
    public function getSraTaskNotImplementedInformationText()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->SraTaskNotImplementedInformationText;
    }

    /**
     * @return string
     */
    public function getSraTaskPlannedInformationText()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->SraTaskPlannedInformationText;
    }

    /**
     * @return string
     */
    public function getSraTaskImplementedInformationText()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->SraTaskImplementedInformationText;
    }

    /**
     * @return string
     */
    public function getSraTaskLikelihoodScoreHelpText()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->SraTaskLikelihoodScoreHelpText;
    }

    /**
     * @return string
     */
    public function getSraTaskImpactScoreHelpText()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->SraTaskImpactScoreHelpText;
    }

    /**
     * @return string
     */
    public function getControlSetSelectionTaskHelpText()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->ControlSetSelectionTaskHelpText;
    }

    /**
     * @return string
     */
    public function getSelectedControls()
    {
        return $this->SelectedControls;
    }

    /**
     * @param string $selectedControls controls
     * @return void
     */
    public function setSelectedControls($selectedControls)
    {
        $this->SelectedControls = $selectedControls;
    }

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        if (!$this->CompletedAt) {
            $fields->removebyName('CompletedAt');
        } else {
            /* @var $completedAtField DatetimeField */
            $completedAtField = $fields->dataFieldByName('CompletedAt');
            $completedAtField
                ->setHTML5(false)
                ->setDatetimeFormat('dd/MM/yyyy hh:mm a')
                ->setReadonly(true)
                ->setDescription(null);
        }

        $updatedAccreditationPeriod = $fields->dataFieldByName('UpdatedAccreditationPeriod');
        $updatedAccreditationPeriod
            ->setReadonly(true)
            ->setDescription('Please add updated accreditation period in the format like: "6 months" or "12 months".');

        // link tab
        $secureLink = $this->SecureLink();
        $anonLink = $this->AnonymousAccessLink();
        $textFieldsforSecureLink = [];
        $textFieldsforAnonymousLink = [];

        foreach ($secureLink as $key => $link) {
            $textFieldsforSecureLink[] = TextField::create('SecureLink_' . $key, 'Link for '.$key)
                ->setValue($link)
                ->setReadonly(true)
                ->setDescription('This is the link emailed to authenticated'
                    .' users of the application');

        }

        foreach ($anonLink as $key => $link) {
            $textFieldsforAnonymousLink[] = TextField::create('AnonymousLink_' . $key, 'Link for '.$key)
                ->setValue($link)
                ->setReadonly(true)
                ->setDescription('This is the link emailed to anonymous users of the application.'
                    .' Anyone possessing the link can view the submission');

        }

        $fields->addFieldsToTab(
            'Root.Links',
            [
                ToggleCompositeField::create(
                    'SecureLinkToggle',
                    'Secure link',
                    $textFieldsforSecureLink
                ),
                ToggleCompositeField::create(
                    'AnonymousLinkToggle',
                    'Anonymous link',
                    $textFieldsforAnonymousLink
                ),
            ]
        );

        $fields->removeByName([
          'RiskResultData',
          'TaskRecommendationData',
          'QuestionnaireData',
          'AnswerData',
          'Result',
          'SubmitterID',
          'TaskApproverID'
        ]);

        $fields->addFieldsToTab(
            'Root.TaskSubmissionData',
            [
                ToggleCompositeField::create(
                    'QuestionnaireDataToggle',
                    'Questionnaire Data',
                    [
                        TextareaField::create('QuestionnaireData'),
                    ]
                ),

                ToggleCompositeField::create(
                    'AnswerDataToggle',
                    'Answer Data',
                    [
                        TextareaField::create('AnswerData'),
                    ]
                ),

                ToggleCompositeField::create(
                    'ResultToggle',
                    'Result',
                    [
                        TextField::create('Result'),
                    ]
                ),
                ToggleCompositeField::create(
                    'ToggleTaskRecommendationData',
                    'Task Recommendation Data',
                    [
                        TextareaField::create('TaskRecommendationData')
                    ]
                )
            ]
        );

        // @TODO: we will work on risk result for multi component in story RM90423
        // https://redmine.catalyst.net.nz/issues/90423
        if ($this->RiskResultData) {
            $riskResultTable = $this->getRiskResultTable();
            if ($riskResultTable) {
                $fields->addFieldsToTab(
                    'Root.TaskSubmissionData',
                    [
                        ToggleCompositeField::create(
                            'ToggleRiskResultData',
                            'Risk Result Data',
                            [
                                TextareaField::create('RiskResultData')
                            ]
                        ),
                        LiteralField::create('RiskResultDataHeader', '<h1>Risk results</h3>'),
                        LiteralField::create('RiskResultDataTable', $riskResultTable),
                    ]
                );
            }
        }

        $taskApproverList = [];

        if ($approvalGroup = $this->ApprovalGroup()) {
            $taskApproverList = $approvalGroup->Members() ?
                $approvalGroup->Members()->map('ID', 'Name') : $taskApproverList;
        }
        $fields->addFieldsToTab(
            'Root.TaskSubmitter',
            [
                DropdownField::create(
                    'SubmitterID',
                    'Submitter',
                    Member::get()->map('ID', 'Name')
                )->setEmptyString(' '),
                $fields->dataFieldByName('SubmitterIPAddress'),
                DropdownField::create(
                    'completedByID',
                    'Completed By',
                    Member::get()->map('ID', 'Name')
                )
                ->setDescription('Task can be completed by submitter or collaborators.')
                ->setEmptyString(' ')
            ]
        );

        $fields->addFieldsToTab(
            'Root.TaskApproval',
            [
                $fields->dataFieldByName('IsApprovalRequired'),
                DropdownField::create(
                    'TaskApproverID',
                    'Task Approver',
                    $taskApproverList
                )->setEmptyString(' '),
                $fields->dataFieldByName('ApprovalGroupID'),
                $fields->dataFieldByName('IsTaskApprovalLinkSent'),
                $fields->dataFieldByName('IsStakeholdersEmailSent')
            ]
        );

        $fields->insertBefore(
            $fields->dataFieldByName('TaskID'),
            'Status'
        );

        $selectedComponentGrid = $fields->dataFieldByName('SelectedComponents');

        if ($selectedComponentGrid) {
            $config = $selectedComponentGrid->getConfig();
            $config->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
            $config->getComponentByType(GridFieldAddNewButton::class)->setButtonName('Add New Component');
        }

        if (!$this->Task()->isRiskType()) {
            $fields->removeByName('RiskResultData');
        }

        $fields->removeByName('QuestionnaireSubmissionID');

        if ($this->Task()->isControlValidationAudit()) {
            $this->getCVA_CMSFields($fields);
        }

        return $fields;
    }

    /**
     * @param SchemaScaffolder $scaffolder The scaffolder of the schema
     *
     * @return void
     */
    public function provideGraphQLScaffolding(SchemaScaffolder $scaffolder)
    {
        $dataObjectScaffolder = $this->provideGraphQLScaffoldingForEntityType($scaffolder);
        $this->provideGraphQLScaffoldingForCreateTaskSubmission($scaffolder);
        $this->provideGraphQLScaffoldingForUpdateTaskSubmission($scaffolder);
        $this->provideGraphQLScaffoldingForCompleteTaskSubmission($scaffolder);
        $this->provideGraphQLScaffoldingForEditTaskSubmission($scaffolder);
        $this->provideGraphQLScaffoldingForUpdateTaskSubmissionWithSelectedComponents($scaffolder);
        $this->provideGraphQLScaffoldingForReadTaskSubmission($dataObjectScaffolder);
        $this->provideGraphQLScaffoldingForUpdateTaskSubmissionStatusToApproved($scaffolder);
        $this->provideGraphQLScaffoldingForUpdateTaskSubmissionStatusToDenied($scaffolder);
        $this->provideGraphQLScaffoldingForUpdateTaskRecommendationData($scaffolder);
        $this->provideGraphQLScaffoldingForUpdateControlValidationAuditTaskSubmission($scaffolder);
        $this->provideGraphQLScaffoldingtoReSyncWithJira($scaffolder);
        $this->provideGraphQLScaffoldingForUpdateControlValidationAuditControlStatus($scaffolder);
        $this->provideGraphQLScaffoldingForUpdateControlValidationAuditControlDetails($scaffolder);
    }

    /**
     * @param SchemaScaffolder $scaffolder The scaffolder of the schema
     * @return DataObjectScaffolder
     */
    private function provideGraphQLScaffoldingForEntityType(SchemaScaffolder $scaffolder)
    {
        $dataObjectScaffolder = $scaffolder
            ->type(TaskSubmission::class)
            ->addFields([
                'ID',
                'UUID',
                'QuestionnaireData',
                'AnswerData',
                'Status',
                'Result',
                'Submitter',
                'TaskApprover',
                'TaskName',
                'TaskType',
                'QuestionnaireSubmission',
                'LockAnswersWhenComplete',
                'JiraKey',
                'IsTaskApprovalRequired',
                'IsCurrentUserAnApprover',
                'RiskResultData',
                'TaskRecommendationData',
                'ComponentTarget',
                'ProductAspects',
                //you would be forgiven for thinking this returns a TaskSubmission
                //it doesn't. It returns the RiskResultData instead.
                'RiskAssessmentTaskSubmission',
                'CVATaskData',
                'CVATaskDataSource',
                'SecurityRiskAssessmentData',
                'Created',
                'CanUpdateTask',
                'IsTaskCollborator',
                'TimeToReview',
                'TimeToComplete',
                'CanTaskCreateNewTasks',
                'InformationClassificationTaskResult',
                'RiskProfileData',
                'ResultForCertificationAndAccreditation',
                'PreventMessage',
                'IsDisplayPreventMessage',
                'SraTaskHelpText',
                'SraTaskRecommendedControlHelpText',
                'SraTaskRiskRatingHelpText',
                'SraTaskLikelihoodScoreHelpText',
                'SraTaskImpactScoreHelpText',
                'CreateOnceInstancePerComponent',
                'SraTaskNotApplicableInformationText',
                'SraTaskNotImplementedInformationText',
                'SraTaskPlannedInformationText',
                'SraTaskImplementedInformationText',
                'ControlSetSelectionTaskHelpText',
                'SelectedControls',
                'LikelihoodRatingsThresholds',
                'RiskRatingThresholdsMatix'
            ]);

        $dataObjectScaffolder
            ->nestedQuery('SelectedComponents')
            ->setResolver(new class implements ResolverInterface {
                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws Exception
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    $selectedComponent = $object->SelectedComponents();
                    $productAspect = json_decode($object->ProductAspects);

                    if (!empty($productAspect)) {
                        return $selectedComponent = $selectedComponent->filter([
                            'ProductAspect' => $productAspect
                        ]);
                    }

                    if (empty($productAspect)) {
                        return $selectedComponent = $selectedComponent->filter([
                            'ProductAspect' => null
                        ]);
                    }
                    return $selectedComponent;
                }
            })
            ->setUsePagination(false)
            ->end();

        $dataObjectScaffolder
            ->nestedQuery('JiraTickets')
            ->setUsePagination(false)
            ->end();

        return $dataObjectScaffolder;
    }

    /**
     * @param SchemaScaffolder $scaffolder The scaffolder of the schema
     * @return void
     */
    private function provideGraphQLScaffoldingForCreateTaskSubmission(SchemaScaffolder $scaffolder)
    {
        $scaffolder
            ->mutation('createTaskSubmission', TaskSubmission::class)
            ->addArgs([
                'TaskID' => 'String!',
                'QuestionnaireSubmissionID' => 'String!'
            ])
            ->setResolver(new class implements ResolverInterface {
                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws Exception
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    // Check authentication
                    QuestionnaireValidation::is_user_logged_in();

                    $taskID = (int)$args['TaskID'];
                    $questionnaireSubmissionID = (int)$args['QuestionnaireSubmissionID'];
                    $submitterID = (int)Security::getCurrentUser()->ID;

                    if (!$taskID || !$questionnaireSubmissionID || !$submitterID) {
                        throw new Exception('Invalid arguments');
                    }

                    $questionnaireSubmission = QuestionnaireSubmission::get_by_id($questionnaireSubmissionID);
                    if (!$questionnaireSubmission->exists()) {
                        throw new Exception('Questionnaire submission does not exist');
                    }
                    if ((int)$questionnaireSubmission->User()->ID !== $submitterID) {
                        throw new Exception('Questionnaire submission does not belong to you');
                    }

                    $taskSubmission = TaskSubmission::create_task_submission(
                        $taskID,
                        $questionnaireSubmissionID,
                        $submitterID
                    );
                }
            })
            ->end();
    }

    /**
     * When the user submit a questionnaire, the system will generate task submissions by calling this method
     *
     * @param string|int $taskID                    The task ID
     * @param string|int $questionnaireSubmissionID The questionnaire submission ID
     * @param int        $submitterID               The submitter ID
     * @param boolean    $isOldSubmission           true for old submissio which has_one task
     * @return TaskSubmission
     * @throws Exception
     */
    public static function create_task_submission($taskID, $questionnaireSubmissionID, $submitterID, $isOldSubmission)
    {
        $task = Task::get_by_id($taskID);

        if (!$task || !$task->exists()) {
            throw new Exception('Task does not exist');
        }

        // Avoid creating duplicated task submission: invalid the existing one first
        /* @var $existingTaskSubmission TaskSubmission */
        $existingTaskSubmission = TaskSubmission::get()
            ->filter([
                'TaskID' => $taskID,
                'QuestionnaireSubmissionID' => $questionnaireSubmissionID,
                'SubmitterID' => $submitterID
            ])
            ->first();

        if ($existingTaskSubmission && ($isOldSubmission ||
            json_encode($task->getQuestionsData()) == $existingTaskSubmission->QuestionnaireData)
        ) {
            // Only turn "in progress" task submissions back if the structure is not changed
            // or if it old submission
            $existingTaskSubmission->Status = TaskSubmission::STATUS_START;

            $existingTaskSubmission->write();

            return $existingTaskSubmission;
        }

        // Create new task submission
        $taskSubmission = TaskSubmission::create();

        // Relations
        $taskSubmission->TaskID = $taskID;
        $taskSubmission->QuestionnaireSubmissionID = $questionnaireSubmissionID;
        $taskSubmission->SubmitterID = $submitterID;
        $taskSubmission->ApprovalGroupID = $task->ApprovalGroup()->ID;

        // Structure of task questionnaire
        $taskSubmission->IsApprovalRequired = $task->IsApprovalRequired;
        $taskSubmission->CreateOnceInstancePerComponent = $task->CreateOnceInstancePerComponent;
        $questionnaireData = $task->getQuestionsData();
        $taskSubmission->QuestionnaireData = json_encode($questionnaireData);

        // Initial status of the submission
        $taskSubmission->Status = TaskSubmission::STATUS_START;
        $taskSubmission->LockAnswersWhenComplete = $task->LockAnswersWhenComplete;

        $taskSubmission->write();

        // after create the task questionnaire, please send a start page link
        // to the submitter
        $qs = QueuedJobService::create();

        $qs->queueJob(
            new SendTaskSubmissionEmailJob($taskSubmission),
            date('Y-m-d H:i:s', time() + 30)
        );

        return $taskSubmission;
    }

    /**
     * @param SchemaScaffolder $scaffolder The scaffolder of the schema
     * @return void
     */
    private function provideGraphQLScaffoldingForUpdateTaskSubmission(SchemaScaffolder $scaffolder)
    {
        $scaffolder
            ->mutation('updateTaskSubmission', TaskSubmission::class)
            ->addArgs([
                'UUID' => 'String!',
                'QuestionID' => 'ID!',
                'AnswerData' => 'String',
                'SecureToken' => 'String',
                'Component' => 'String',
            ])
            ->setResolver(new class implements ResolverInterface {
                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws Exception
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    if (empty($args['UUID']) || empty($args['QuestionID']) ||empty($args['AnswerData'])) {
                        throw new Exception('Please enter a valid argument data.');
                    }

                    $member = Security::getCurrentUser();
                    $uuid = Convert::raw2sql($args['UUID']);
                    $secureToken = isset($args['SecureToken']) ? Convert::raw2sql(trim($args['SecureToken'])) : null;
                    $component = isset($args['Component']) ? Convert::raw2sql(trim($args['Component'])) : null;

                    $submission = TaskSubmission::get_task_submission_by_uuid($uuid);

                    $canEdit = TaskSubmission::can_edit_task_submission(
                        $submission,
                        $member,
                        $secureToken
                    );
                    if (!$canEdit) {
                        throw new GraphQLAuthFailure();
                    }

                    // AnswerData is generated by `window.btoa(JSON.stringify(answerData))` in JavaScript
                    // This is to avoid parsing issue caused by `quote`, `\n` and other special characters
                    $questionAnswerData = json_decode(base64_decode($args['AnswerData']));

                    if (is_null($questionAnswerData)) {
                        throw new Exception('data is not a vaild json object.');
                    }

                    // Validate answer data
                    do {
                        // If there is no answer or not applicable, don't validate it
                        // Scenario: only use this API to save "current" and "applicable" flag
                        if ((bool)($questionAnswerData->hasAnswer) === false) {
                            break;
                        }
                        if ((bool)($questionAnswerData->isApplicable) === false) {
                            break;
                        }

                        if ($questionAnswerData->answerType == "input") {
                            // validate input field data
                            QuestionnaireValidation::validate_answer_input_data(
                                $questionAnswerData->inputs,
                                $submission->QuestionnaireData,
                                $args['QuestionID']
                            );
                        }

                        if ($questionAnswerData->answerType == "action") {
                            //validate action field
                            QuestionnaireValidation::validate_answer_action_data(
                                $questionAnswerData->actions,
                                $submission->QuestionnaireData,
                                $args['QuestionID']
                            );
                        }
                    } while (false);

                    $answerDataArr = [];

                    if (!empty($submission->AnswerData)) {
                        $answerDataArr = json_decode($submission->AnswerData, true);
                    }

                    $answerDataArr[$args['QuestionID']] = $questionAnswerData;

                    // if everything is ok, then please add/update AnswerData
                    $allAnswerData = json_decode($submission->AnswerData, true);

                    if ($submission->checkForMultiComponent($component)) {
                        $doesComponentExistInAnswerData = 0;

                        if(!empty($allAnswerData)) {
                            foreach ($allAnswerData as $key => $answerDataForComponent) {
                                if ($answerDataForComponent['productAspect'] == $component) {
                                    $doesComponentExistInAnswerData = 1;
                                    $result = $answerDataForComponent['result'];
                                    $result[$args['QuestionID']] = $questionAnswerData;
                                    $answerDataForComponent['result'] = $result;
                                    $allAnswerData[$key] = $answerDataForComponent;
                                }
                            }
                        }

                        if (!$doesComponentExistInAnswerData ) {
                            $answerDataForComponent['productAspect'] = $component;
                            $answerDataForComponent['status'] = TaskSubmission::STATUS_IN_PROGRESS;
                            $result[$args['QuestionID']] = $questionAnswerData;
                            $answerDataForComponent['result'] = $result;
                            $allAnswerData[] = $answerDataForComponent;
                        }
                    } else {
                        $allAnswerData[$args['QuestionID']] = $questionAnswerData;
                    }

                    $submission->AnswerData = json_encode($allAnswerData);
                    $submission->Status = TaskSubmission::STATUS_IN_PROGRESS;
                    $submission->completedByID = (int)(
                        Security::getCurrentUser() ? Security::getCurrentUser()->ID : 0
                    );

                    $submission->write();

                    return $submission;
                }
            })
            ->end();
    }

    /**
     * change task submission status to in-progress and re-load the data from JIRA\
     *
     * @param SchemaScaffolder $scaffolder The scaffolder of the schema
     * @return void
     */
    public function provideGraphQLScaffoldingtoReSyncWithJira(SchemaScaffolder $scaffolder)
    {
        $scaffolder
            ->mutation('reSyncWithJira', TaskSubmission::class)
            ->addArgs([
                'UUID' => 'String!'
            ])
            ->setResolver(new class implements ResolverInterface {
                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws GraphQLAuthFailure
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    $member = Security::getCurrentUser();
                    $uuid = Convert::raw2sql($args['UUID']);
                    $submission = TaskSubmission::get_task_submission_by_uuid($uuid);
                    $canEdit = TaskSubmission::can_edit_task_submission(
                        $submission,
                        $member,
                        ''
                    );
                    if (!$canEdit) {
                        throw new GraphQLAuthFailure();
                    }

                    $submission->Status = TaskSubmission::STATUS_IN_PROGRESS;
                    $submission->write();

                    if ($submission->TaskType === 'control validation audit') {
                        $siblingComponentSelectionTask = $submission->getSiblingTaskSubmissionsByType('selection');

                        if (empty($data->CVATaskData)) {
                            $submission->CVATaskData = $submission->getDataforCVATask($siblingComponentSelectionTask);
                        }
                    }
                    return $submission;
                }
            })
            ->end();
    }

    /**
     * @param SchemaScaffolder $scaffolder The scaffolder of the schema
     * @return void
     */
    private function provideGraphQLScaffoldingForUpdateControlValidationAuditTaskSubmission(SchemaScaffolder $scaffolder)
    {
        $scaffolder
            ->mutation('updateControlValidationAuditTaskSubmission', TaskSubmission::class)
            ->addArgs([
                'UUID' => 'String!',
                'CVATaskData' => 'String'
            ])
            ->setResolver(new class implements ResolverInterface {
                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws GraphQLAuthFailure
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    $member = Security::getCurrentUser();
                    $uuid = Convert::raw2sql($args['UUID']);
                    $submission = TaskSubmission::get_task_submission_by_uuid($uuid);
                    $canEdit = TaskSubmission::can_edit_task_submission(
                        $submission,
                        $member,
                        ''
                    );
                    if (!$canEdit) {
                        throw new GraphQLAuthFailure();
                    }
                    $submission->CompletedAt = date('Y-m-d H:i:s');
                    $submission->CVATaskData = base64_decode($args['CVATaskData']);

                    // set Submitter IP Address
                    if ($_SERVER['REMOTE_ADDR']) {
                        $submission->SubmitterIPAddress = Convert::raw2sql($_SERVER['REMOTE_ADDR']);
                    }

                    $submission->Status = TaskSubmission::STATUS_COMPLETE;
                    $submission->completedByID = $member->ID;
                    $submission->sendEmailToStakeholder();

                    // if task approval requires then set status to waiting for approval
                    if ($submission->IsTaskApprovalRequired) {
                        $submission->setStatusToWatingforApproval();
                    }

                    $submission->write();
                    $submission->sendAllTheTasksCompletedEmail();
                    return $submission;
                }
            })
            ->end();
    }

    /**
     * @param SchemaScaffolder $scaffolder The scaffolder of the schema
     * @return void
     */
    private function provideGraphQLScaffoldingForUpdateControlValidationAuditControlStatus(SchemaScaffolder $scaffolder)
    {
        $scaffolder
            ->mutation('updateControlValidationAuditControlStatus', TaskSubmission::class)
            ->addArgs([
                'UUID' => 'String!',
                'ComponentID' => 'String',
                'ControlID' => 'String',
                'ProductAspect' => 'String',
                'SelectedOption' => 'String'
            ])

            ->setResolver(new class implements ResolverInterface {
                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws GraphQLAuthFailure
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    $member = Security::getCurrentUser();
                    $uuid = Convert::raw2sql($args['UUID']);
                    $submission = TaskSubmission::get_task_submission_by_uuid($uuid);
                    $canEdit = TaskSubmission::can_edit_task_submission(
                        $submission,
                        $member,
                        ''
                    );

                    if (!$canEdit) {
                        throw new GraphQLAuthFailure();
                    }

                    $componentID = isset($args['ComponentID']) ? Convert::raw2sql(trim($args['ComponentID'])) : null;
                    $controlID = isset($args['ControlID']) ? Convert::raw2sql(trim($args['ControlID'])) : null;
                    $productAspect = isset($args['ProductAspect']) ? Convert::raw2sql(trim($args['ProductAspect'])) : null;
                    $controlStatus = isset($args['SelectedOption']) ? Convert::raw2sql(trim($args['SelectedOption'])) : null;

                    $cvaData = json_decode($submission->CVATaskData, true);

                    if (!empty($cvaData)) {
                        foreach ($cvaData as $componentKey => $data) {
                            if ($productAspect == $data['productAspect'] && $componentID == $data['id']) {
                                if (!empty($data['controls'])) {
                                    foreach ($data['controls'] as $controlKey => $control) {
                                        if ($control['id'] == $controlID) {
                                            $control['selectedOption'] = $controlStatus;
                                            $data['controls'][$controlKey] = $control;
                                        }
                                    }
                                }
                            }
                            $cvaData[$componentKey] = $data;
                        }
                    }

                    $cvaTaskData = json_encode($cvaData);

                    $selectedControls = $cvaTaskData;

                    if (!empty($cvaTaskData) && $productAspect) {
                        $selectedControls = $submission->filterCVAResultForComponent($cvaTaskData, $productAspect);
                    }

                    // this data is used to display cards on the sra task screen
                    $submission->setSelectedControls($selectedControls);
                    $submission->CVATaskData = json_encode($cvaData);
                    $submission->write();

                    // first save data into cva task submissions
                    // then only set the updated sra result
                    $siblingSraTask = $submission->getSiblingTaskSubmissionsByType('security risk assessment');
                    $sraData = $submission->updateSecurityRiskAssessmentData(
                        $productAspect,
                        $submission::STATUS_IN_PROGRESS,
                        $siblingSraTask
                    );
                    $submission->setSecurityRiskAssessmentData($sraData);

                    return $submission;
                }
            })
            ->end();
    }

    /**
     * @param SchemaScaffolder $scaffolder The scaffolder of the schema
     * @return void
     */
    private function provideGraphQLScaffoldingForUpdateControlValidationAuditControlDetails(SchemaScaffolder $scaffolder)
    {
        $scaffolder
            ->mutation('updateControlValidationAuditControlDetails', TaskSubmission::class)
            ->addArgs([
                'UUID' => 'String!',
                'ComponentID' => 'String',
                'ControlID' => 'String',
                'ProductAspect' => 'String',
                'UpdatedControl' => 'String'
            ])

            ->setResolver(new class implements ResolverInterface {
                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws GraphQLAuthFailure
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    $member = Security::getCurrentUser();
                    $uuid = Convert::raw2sql($args['UUID']);
                    $submission = TaskSubmission::get_task_submission_by_uuid($uuid);
                    $canEdit = TaskSubmission::can_edit_task_submission(
                        $submission,
                        $member,
                        ''
                    );

                    if (!$canEdit) {
                        throw new GraphQLAuthFailure();
                    }

                    $componentID = isset($args['ComponentID']) ? Convert::raw2sql(trim($args['ComponentID'])) : null;
                    $controlID = isset($args['ControlID']) ? Convert::raw2sql(trim($args['ControlID'])) : null;
                    $productAspect = isset($args['ProductAspect']) ? Convert::raw2sql(trim($args['ProductAspect'])) : null;
                    $updatedControl = isset($args['UpdatedControl']) ? Convert::raw2sql(trim($args['UpdatedControl'])) : null;
                    $updatedControlArray = json_decode(base64_decode($updatedControl), true);
                    $cvaData = json_decode($submission->CVATaskData, true);

                    if (!empty($cvaData)) {
                        foreach ($cvaData as $componentKey => $data) {
                            if ($productAspect == $data['productAspect'] && $componentID == $data['id']) {
                                if (!empty($data['controls'])) {
                                    foreach ($data['controls'] as $controlKey => $control) {
                                        if (isset($control["id"]) && $control["id"] == $controlID) {
                                            $data['controls'][$controlKey] = $updatedControlArray;
                                        }
                                    }
                                }
                            }
                            $cvaData[$componentKey] = $data;
                        }
                    }

                    // this data is used to display cards on the sra task screen
                    $submission->CVATaskData = json_encode($cvaData);
                    $submission->write();

                    // first save data into cva task submissions
                    // then only set the updated sra result
                    $siblingSraTask = $submission->getSiblingTaskSubmissionsByType('security risk assessment');
                    $sraData = $submission->updateSecurityRiskAssessmentData(
                        $productAspect,
                        $submission::STATUS_IN_PROGRESS,
                        $siblingSraTask
                    );
                    $submission->setSecurityRiskAssessmentData($sraData);

                    return $submission;
                }
            })
            ->end();
    }

    /**
     * @param SchemaScaffolder $scaffolder The scaffolder of the schema
     * @return void
     */
    private function provideGraphQLScaffoldingForCompleteTaskSubmission(SchemaScaffolder $scaffolder)
    {
        $scaffolder
            ->mutation('completeTaskSubmission', TaskSubmission::class)
            ->addArgs([
                'UUID' => 'String!',
                'Result' => 'String',
                'SecureToken' => 'String',
                'Component' => 'String',
            ])
            ->setResolver(new class implements ResolverInterface {
                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws GraphQLAuthFailure
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    $member = Security::getCurrentUser();
                    $uuid = Convert::raw2sql($args['UUID']);
                    $component = isset($args['Component']) ? Convert::raw2sql(trim($args['Component'])) : null;
                    $secureToken = isset($args['SecureToken']) ? Convert::raw2sql(trim($args['SecureToken'])) : null;

                    $submission = TaskSubmission::get_task_submission_by_uuid($uuid);

                    $canEdit = TaskSubmission::can_edit_task_submission(
                        $submission,
                        $member,
                        $secureToken
                    );

                    if (!$canEdit) {
                        throw new GraphQLAuthFailure();
                    }

                    // this answer data of seletced component will be used
                    // to create another task for multicomponent
                    $answerDataResultForSelectedComponent = $submission->completeTaskSubmission($component);

                    // If it's a vendor task and completed by anonymous user,
                    // mark the userid to be 0.
                    if ($member) {
                        $submission->completedByID = $member->ID;
                    } else {
                        $submission->completedByID = 0;
                    }
                    $submission->sendEmailToStakeholder();

                    // if task approval requires then set status to waiting for approval
                    if ($submission->IsTaskApprovalRequired) {
                        $submission->setStatusToWatingforApproval();
                    }

                    if ($_SERVER['REMOTE_ADDR']) {
                        $submission->SubmitterIPAddress = Convert::raw2sql($_SERVER['REMOTE_ADDR']);
                    }

                    $submission->CompletedAt = date('Y-m-d H:i:s');

                    // TODO: validate based on answer
                    if (isset($args['Result'])) {
                        $submission->Result = trim($args['Result']);
                    }

                    if ($submission->TaskType === 'selection') {
                        $siblingCVATask = $submission->getSiblingTaskSubmissionsByType('control validation audit');
                        $siblingCVATask->CVATaskData = $submission->getDataforCVATask($submission);
                        $siblingCVATask->write();
                    }

                    // create another tasks form task submission based on task submission's answer
                    if ($submission->TaskType === 'questionnaire' || $submission->TaskType === 'risk questionnaire') {
                        Question::create_task_submissions_according_to_answers(
                            $submission->QuestionnaireData,
                            $submission->checkForMultiComponent($component) ? json_encode($answerDataResultForSelectedComponent) : $submission->AnswerData,
                            $submission->QuestionnaireSubmissionID,
                            '',
                            $secureToken,
                            'ts'
                        );
                    }

                    TaskSubmission::findAndSetDefaultCvaTaskData($submission->QuestionnaireSubmission());

                    $submission->write();
                    $submission->sendAllTheTasksCompletedEmail();

                    return $submission;
                }
            })
            ->end();
    }

    /**
     * Check for multi component feature
     *
     * @param string $component component exist in the url
     * @return bool
     */
    public function checkForMultiComponent($component) : bool
    {
        if ($component && !empty($this->getProductAspects()) && $this->CreateOnceInstancePerComponent) {
            return true;
        }

        return false;
    }

    /**
     * set task submission status to waitig for approval
     * and send email to the approver and stakeholder
     * @return void
     */
    public function setStatusToWatingforApproval() : void
    {
        $this->Status = TaskSubmission::STATUS_WAITING_FOR_APPROVAL;

        if (!$this->IsTaskApprovalLinkSent) {
            $members = $this->approvalGroupMembers();
            $this->IsTaskApprovalLinkSent = 1;

            // send approval link email to the approver group
            if ($members->exists()) {
                $qs = QueuedJobService::create();

                $qs->queueJob(
                    new SendTaskApprovalLinkEmailJob($this, $members),
                    date('Y-m-d H:i:s', time() + 30)
                );
            }
        }

        $this->SendEmailToStakeholder();
    }

    /**
     * @param SchemaScaffolder $scaffolder The scaffolder of the schema
     * @return void
     */
    private function provideGraphQLScaffoldingForEditTaskSubmission(SchemaScaffolder $scaffolder)
    {
        $scaffolder
            ->mutation('editTaskSubmission', TaskSubmission::class)
            ->addArgs([
                'UUID' => 'String!',
                'SecureToken' => 'String',
                'Component' => 'String'
            ])
            ->setResolver(new class implements ResolverInterface {
                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws GraphQLAuthFailure
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    $member = Security::getCurrentUser();
                    $uuid = Convert::raw2sql($args['UUID']);
                    $component = isset($args['Component']) ? Convert::raw2sql(trim($args['Component'])) : null;
                    $secureToken = isset($args['SecureToken']) ? Convert::raw2sql(trim($args['SecureToken'])) : null;
                    $submission = TaskSubmission::get_task_submission_by_uuid($uuid);
                    $canEdit = TaskSubmission::can_edit_task_submission(
                        $submission,
                        $member,
                        $secureToken
                    );

                    if (!$canEdit) {
                        throw new GraphQLAuthFailure();
                    }

                    if ($submission->checkForMultiComponent($component)) {
                        $allAnswerData = json_decode($submission->AnswerData, true);

                        if(!empty($allAnswerData)) {
                            foreach ($allAnswerData as $key => $answerDataForComponent) {
                                if ($answerDataForComponent['productAspect'] == $component) {
                                    $answerDataForComponent['status'] = TaskSubmission::STATUS_IN_PROGRESS;
                                    $allAnswerData[$key] = $answerDataForComponent;
                                    $submission->AnswerData = json_encode($allAnswerData);
                                }
                            }
                        }
                    }

                    $submission->Status = TaskSubmission::STATUS_IN_PROGRESS;
                    $submission->SubmitterIPAddress = null;
                    $submission->CompletedAt = null;
                    $submission->Result = null;

                    // if task type is component selection, then delete
                    // it's sibling CSV task data
                    if ($submission->TaskType === 'selection' &&
                        $siblingCVATask = $submission->getSiblingTaskSubmissionsByType("control validation audit")) {
                        $siblingCVATask->CVATaskData = '';
                        $siblingCVATask->Status = TaskSubmission::STATUS_START;
                        $siblingCVATask->write();
                    }
                    $submission->write();

                    return $submission;
                }
            })
            ->end();
    }

    /**
     * @param DataObjectScaffolder $scaffolder The scaffolder of the data object
     * @return void
     */
    private function provideGraphQLScaffoldingForReadTaskSubmission(DataObjectScaffolder $scaffolder)
    {
        $scaffolder
            ->operation(SchemaScaffolder::READ)
            ->setName('readTaskSubmission')
            ->addArg('UUID', 'String')
            ->addArg('UserID', 'String')
            ->addArg('SecureToken', 'String')
            ->addArg('Component', 'String')
            ->addArg('PageType', 'String')
            ->setUsePagination(false)
            ->setResolver(new class implements ResolverInterface {

                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws Exception
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    $member = Security::getCurrentUser();
                    $uuid = isset($args['UUID']) ? Convert::raw2sql(trim($args['UUID'])) : null;
                    $userID = isset($args['UserID']) ? (int) $args['UserID'] : null;
                    $secureToken = isset($args['SecureToken']) ? Convert::raw2sql(trim($args['SecureToken'])) : null;
                    $component = isset($args['Component']) ? Convert::raw2sql(trim($args['Component'])) : null;

                    // Check argument
                    if (!$uuid && !$userID) {
                        throw new Exception('Sorry, there is no UUID or user Id.');
                    }

                    if (!empty($userID) && $member->ID != $userID) {
                        throw new Exception('Sorry, wrong user Id.');
                    }

                    $data = [];

                    if ($uuid) {
                        // Filter data by UUID
                        /* @var $data TaskSubmission */
                        $data = TaskSubmission::get()
                            ->filter(['UUID' => $uuid])
                            ->exclude('Status', TaskSubmission::STATUS_INVALID)
                            ->first();

                        $canView = TaskSubmission::can_view_task_submission(
                            $data,
                            $member,
                            $secureToken
                        );

                        if (!$canView) {
                            throw new GraphQLAuthFailure();
                        }

                        $data->ProductAspects = $data->QuestionnaireSubmissionID ?
                            $data->QuestionnaireSubmission()->getProductAspects(): '{}';

                        if ($data->TaskType === 'security risk assessment') {
                            $sraData = $data->getSraResult($component);
                            // this data is used to display sra table
                            $data->setSecurityRiskAssessmentData($sraData);

                            $siblingCVATask = $data->getSiblingTaskSubmissionsByType('control validation audit');
                            $cvaTaskData = '';

                            // get CVA task data to display controls
                            if ($siblingCVATask) {
                                $cvaTaskData = $siblingCVATask->CVATaskData;
                            }

                            $selectedControls = $cvaTaskData;

                            if (!empty($cvaTaskData) && $data->checkForMultiComponent($component)) {
                                $selectedControls = $data->filterCVAResultForComponent($cvaTaskData, $component);
                            }

                            // this data is used to display cards on the sra task screen
                            $data->setSelectedControls($selectedControls);
                        }
                    }

                    return $data;
                }
            })
            ->end();
    }

    /**
     * When there are multiple components,
     * filter CVATaskData to return data related to specific component
     *
     * @param mixed $cvaTaskData CVATaskData
     * @param string $component selected product aspect
     *
     * @return string
     */
    public function filterCVAResultForComponent($cvaTaskData, $component) : string
    {
        if (empty($cvaTaskData)) {
            return '';
        }
        $cvaDataArray = json_decode($cvaTaskData, true);
        $finalFilterCVAResult = [];

        $filteredCVAResult = array_filter($cvaDataArray, function($cvaData) use ($component) {
            return $cvaData["productAspect"] == $component;
        });

        if (!empty($filteredCVAResult)) {
            foreach ($filteredCVAResult as $key => $value) {
                $finalFilterCVAResult[]= $value;
            }
        }

        return json_encode($finalFilterCVAResult);
    }

    /**
     * Change task submission status to approve
     *
     * @param SchemaScaffolder $scaffolder SchemaScaffolder
     *
     * @return void
     */
    public function provideGraphQLScaffoldingForUpdateTaskSubmissionStatusToApproved(SchemaScaffolder $scaffolder)
    {
        $scaffolder
            ->mutation('updateTaskStatusToApproved', TaskSubmission::class)
            ->addArg('UUID', 'String!')
            ->setResolver(new class implements ResolverInterface {
                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws Exception
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    // Check authentication
                    QuestionnaireValidation::is_user_logged_in();

                    $member = Security::getCurrentUser();
                    $uuid = Convert::raw2sql($args['UUID']);

                    if (empty($args['UUID'])) {
                        throw new Exception('Please enter a valid argument data.');
                    }

                    $submission = TaskSubmission::get_task_submission_by_uuid($uuid);

                    if (!$submission) {
                        throw new Exception('No data available for Task Submission.');
                    }
                    //throw new Exception(TaskSubmission::STATUS_WAITING_FOR_APPROVAL);

                    if ($submission->Status != TaskSubmission::STATUS_WAITING_FOR_APPROVAL) {
                        throw new Exception('Task Submission is not ready for approval.');
                    }

                    $submission->Status = TaskSubmission::STATUS_APPROVED;
                    $submission->TaskApproverID = $member->ID;
                    $submission->write();
                    $submission->sendAllTheTasksCompletedEmail();

                    return $submission;
                }
            })
            ->end();
    }

    /**
     * Change task submission status to Deny
     *
     * @param SchemaScaffolder $scaffolder SchemaScaffolder
     *
     * @return void
     */
    public function provideGraphQLScaffoldingForUpdateTaskSubmissionStatusToDenied(SchemaScaffolder $scaffolder)
    {
        $scaffolder
            ->mutation('updateTaskStatusToDenied', TaskSubmission::class)
            ->addArg('UUID', 'String!')
            ->setResolver(new class implements ResolverInterface {
                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws Exception
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    // Check authentication
                    QuestionnaireValidation::is_user_logged_in();

                    $member = Security::getCurrentUser();
                    $uuid = Convert::raw2sql($args['UUID']);

                    if (empty($args['UUID'])) {
                        throw new Exception('Please enter a valid argument data.');
                    }

                    $submission = TaskSubmission::get_task_submission_by_uuid($uuid);

                    if (!$submission) {
                        throw new Exception('No data available for Task Submission.');
                    }

                    if ($submission->Status != TaskSubmission::STATUS_WAITING_FOR_APPROVAL) {
                        throw new Exception('Task Submission is not ready for approval.');
                    }

                    $submission->Status = TaskSubmission::STATUS_DENIED;
                    $submission->TaskApproverID = $member->ID;
                    $submission->write();

                    return $submission;
                }
            })
            ->end();
    }

    /**
     * add/update task recommendation
     *
     * @param SchemaScaffolder $scaffolder SchemaScaffolder
     *
     * @return void
     */
    public function provideGraphQLScaffoldingForUpdateTaskRecommendationData(SchemaScaffolder $scaffolder)
    {
        $scaffolder
            ->mutation('updateTaskRecommendation', TaskSubmission::class)
            ->addArgs([
                'UUID' => 'String!',
                'TaskRecommendationData' => 'String',
            ])
            ->setResolver(new class implements ResolverInterface {
                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws Exception
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    // Check authentication
                    QuestionnaireValidation::is_user_logged_in();

                    $member = Security::getCurrentUser();
                    $uuid = Convert::raw2sql($args['UUID']);

                    if (empty($args['UUID'])) {
                        throw new Exception('Please enter a valid argument data.');
                    }

                    $submission = TaskSubmission::get_task_submission_by_uuid($uuid);

                    if (!$submission) {
                        throw new Exception('No data available for Task Submission.');
                    }

                    if ($submission->Status != TaskSubmission::STATUS_WAITING_FOR_APPROVAL) {
                        throw new Exception('Task Submission is not ready for approval.');
                    }

                    $submission->TaskRecommendationData = json_encode(json_decode(base64_decode($args['TaskRecommendationData'])));
                    $submission->write();

                    return $submission;
                }
            })
            ->end();
    }

    /**
     * check does task belong to log in user
     *
     * @throws Exception
     * @return void
     */
    public function doesTaskSubmissionBelongToCurrentUser()
    {
        $member = Security::getCurrentUser();

        if ((int)($member->ID) !== (int)($this->SubmitterID)) {
            throw new Exception('Sorry Task Submission does not belong to login user.');
        }
    }

    /**
     * check does task submission Exist
     *
     * @param string $uuid uuid
     *
     * @throws Exception
     * @return TaskSubmission
     */
    public static function get_task_submission_by_uuid($uuid = null)
    {
        // Check argument
        if (!$uuid) {
            throw new Exception('Please enter a valid UUID.');
        }

        /* @var $submission TaskSubmission */
        $submission = TaskSubmission::get()->find('UUID', $uuid);

        if (!$submission) {
            throw new Exception('Task submission does not exist');
        }

        return $submission;
    }

    /**
     * @param TaskSubmission $taskSubmission The task submission
     * @param Member|null    $member         The member
     * @param string         $secureToken    The secure token
     * @return bool
     */
    public static function can_view_task_submission($taskSubmission, $member = null, $secureToken = '')
    {
        if (!$taskSubmission) {
            return false;
        }

        // If logged in
        if ($member !== null) {
            // All log in user can view it
            return true;
        }


        // Correct SecureToken can view it
        if ($taskSubmission->SecureToken && @hash_equals($taskSubmission->SecureToken, $secureToken)) {
            return true;
        }

        // Correct ApprovalLinkToken can view it
        $qs = $taskSubmission->QuestionnaireSubmission();
        if ($qs->exists() &&
            $qs->ApprovalLinkToken &&
            @hash_equals($qs->ApprovalLinkToken, $secureToken)) {
            return true;
        }

        // Others can not view it
        return false;
    }

    /**
     * @param TaskSubmission $taskSubmission The task submission
     * @param Member|null    $member         The member
     * @param string         $secureToken    The secure token
     * @return bool
     */
    public static function can_edit_task_submission($taskSubmission, $member = null, $secureToken = '')
    {
        if (!$taskSubmission) {
            return false;
        }

        // A logged-in user will be judged by its role
        if ($member) {
            $isSubmitter = (int)$taskSubmission->SubmitterID === (int)$member->ID;

            $isSA = $member
                ->Groups()
                ->filter('Code', GroupExtension::security_architect_group()->Code)
                ->exists();

            $isCollborator = $taskSubmission->getIsTaskCollborator();

            // Submitter can edit when answers are not locked
            if ($isSubmitter || $isCollborator) {
                if ($taskSubmission->Status === TaskSubmission::STATUS_IN_PROGRESS ||
                    $taskSubmission->Status === TaskSubmission::STATUS_START ||
                    $taskSubmission->Status === TaskSubmission::STATUS_DENIED) {
                    return true;
                }

                if ($taskSubmission->Status === TaskSubmission::STATUS_COMPLETE ||
                    $taskSubmission->Status === TaskSubmission::STATUS_WAITING_FOR_APPROVAL) {
                    if (!$taskSubmission->LockAnswersWhenComplete) {
                        return true;
                    }
                }
            }

            // for c&a memo task only CertificationAndAccreditationGroup memeber
            // can update the task
            $accessGroup = $taskSubmission->Task()->CertificationAndAccreditationGroup();

            if (Member::currentUser()->inGroup($accessGroup->ID) &&
                $taskSubmission->Task()->isCertificationAndAccreditationType()) {
                return true;
            }

            $isTaskApprover = $taskSubmission->getIsTaskApprover();
            if ($isTaskApprover &&
                $taskSubmission->Status === TaskSubmission::STATUS_WAITING_FOR_APPROVAL) {
                return true;
            }

            // SA can edit it
            if ($isSA) {
                return true;
            }
        }

        // Any user with correct SecureToken can edit when answers are not locked
        if ($taskSubmission->SecureToken && @hash_equals($taskSubmission->SecureToken, $secureToken)) {
            if ($taskSubmission->Status === TaskSubmission::STATUS_IN_PROGRESS ||
                $taskSubmission->Status === TaskSubmission::STATUS_START) {
                return true;
            }
            if ($taskSubmission->Status === TaskSubmission::STATUS_COMPLETE) {
                if (!$taskSubmission->LockAnswersWhenComplete) {
                    return true;
                }
            }
        }

        // Disallow editing in other cases
        return false;
    }

    /**
     * onbeforewrite
     *
     * @return void
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->UUID) {
            $this->UUID = (string) Uuid::uuid4();
        }

        if (!$this->SecureToken) {
            $this->SecureToken = hash('sha3-256', random_bytes(64));
        }

        $this->audit();
    }

    /**
     * Encapsulates all model-specific auditing processes.
     *
     * @return void
     */
    protected function audit() : void
    {
        $user = Security::getCurrentUser();

        if (!$user) {
            $user = $this->Submitter();
        }

        $userData = '';

        if ($user) {
            $groups = $user->Groups()->column('Title');
            $userData = implode('. ', [
                'Email: ' . $user->Email,
                'Group(s): ' . ($groups ? implode(' : ', $groups) : 'N/A'),
            ]);
        }

        // audit log: for a task submission
        $doAudit = !$this->exists() && $user;
        if ($doAudit) {
            $msg = sprintf('"%s" was submitted. (UUID: %s)', $this->Task()->Name, $this->UUID);
            $this->auditService->commit('Submit', $msg, $this, $userData);
        }

        // audit log: when task status changed back to in_progress
        $doAudit = $this->exists() && $user;
        $changed = $this->getChangedFields(['Status'], 1);

        if ($doAudit && isset($changed['Status']) &&
            $changed['Status']['before'] !== 'in_progress' &&
            $changed['Status']['after'] == 'in_progress') {
            $msg = sprintf(
                '"%s" had its status changed from "%s" to "%s". (UUID: %s)',
                $this->Task()->Name,
                $changed['Status']['before'],
                $changed['Status']['after'],
                $this->UUID
            );
            $this->auditService->commit('Change', $msg, $this, $userData);
        }

        // audit log: for task submission approval by approval group member
        $hasAccess = $user || $user->Groups()->filter('Code', $this->ApprovalGroup()->Code)->exists();
        $doAudit = $this->exists() && $hasAccess;

        if ($doAudit && isset($changed['Status']) &&
            in_array($changed['Status']['after'], ['approved', 'denied', 'complete'])) {
            $msg = sprintf(
                '"%s" was %s. (UUID: %s)',
                $this->Task()->Name,
                $changed['Status']['after'] !== 'complete' ? $changed['Status']['after']:  'completed',
                $this->UUID
            );

            if ($changed['Status']['after'] == 'complete') {
                $status = $changed['Status']['after'] = 'Complete';
            } else {
                $status = ($changed['Status']['after'] === 'approved') ? 'Approve' : 'Deny';
            }

            $this->auditService->commit($status, $msg, $this, $userData);
        }
    }

    /**
     * Display a link to the task submission.
     * This also generates an email link, which always sures the submission is
     * routed properly in case the user is not logged in when receiving the
     * email
     *
     * Not used directly, it's only for generating SecureLink or AnonymousAccessLink
     *
     * @return string
     */
    public function Link()
    {
        if ($this->Task()->TaskType == 'selection') {
            return "#/component-selection/submission/{$this->UUID}";
        }
        if ($this->TaskType == 'control validation audit') {
            return "#/control-validation-audit/submission/{$this->UUID}";
        }
        if ($this->TaskType == 'security risk assessment') {
            return "#/security-risk-assessment/submission/{$this->UUID}";
        }
        return '#/task/submission/' . $this->UUID;
    }

    /**
     * Check login status first before viewing the task submission
     *
     * @return array
     */
    public function SecureLink()
    {
        $hostname = $this->getHostname();
        $route = $this->Link();
        $links = [];
        $productAspects = json_decode($this->getProductAspects());

        if (!empty($productAspects) && $this->CreateOnceInstancePerComponent) {
            foreach ($productAspects as $component) {
                $routeWithComponent = $route . "?component=" . $component;
                $secureLink = 'Security/login/?BackURL='.rawurlencode($routeWithComponent);
                $links[$component] = $hostname . $secureLink;
            }
        } else {
            $secureLink = 'Security/login/?BackURL='.rawurlencode($route);
            $links["secure user"] = $hostname . $secureLink;

        }

        return $links;
    }

    /**
     * Anonymous access link
     * Allows vendors to login to view the task with a secure token
     *
     * @param string $prefix controller route to follow that grants user access
     *                       for GCIO105, this is 'vendorApp'
     * @return array
     */
    public function AnonymousAccessLink($prefix = 'vendorApp')
    {
        $hostname = $this->getHostname();
        $route = $this->Link();
        $links = [];
        $productAspects = json_decode($this->getProductAspects());

        if (!empty($productAspects) && $this->CreateOnceInstancePerComponent) {
            foreach ($productAspects as $component) {
                $anonLink = sprintf(
                    "%s/%s?token=%s&component=%s",
                    $prefix,
                    $route,
                    $this->SecureToken,
                    $component
                );
                $links[$component] = $hostname . $anonLink;
            }
        } else {
            $anonLink = sprintf(
                "%s/%s?token=%s",
                $prefix,
                $route,
                $this->SecureToken
            );
            $links["anonymous user"] = $hostname . $anonLink;
        }

        return $links;
    }

    /**
     * This is used by f/e logic for task submissions of _both_ ticket ("JIRA Cloud")
     * and "Local" types. It will create local records for selected components.
     *
     * @param  SchemaScaffolder $scaffolder scaffolder
     * @return void
     */
    private function provideGraphQLScaffoldingForUpdateTaskSubmissionWithSelectedComponents(SchemaScaffolder $scaffolder)
    {
        $scaffolder
            ->mutation('updateTaskSubmissionWithSelectedComponents', TaskSubmission::class)
            ->addArgs([
                'UUID' => 'String!',
                'Components' => 'String!',
                'JiraKey' => 'String?' // "Local" targets will pass an empty string in the f/e
            ])
            ->setResolver(new class implements ResolverInterface {
                /**
                 * Invoked by the Executor class to resolve this mutation / query
                 * @see Executor
                 *
                 * @param mixed       $object  object
                 * @param array       $args    args
                 * @param mixed       $context context
                 * @param ResolveInfo $info    info
                 * @throws Exception
                 * @return mixed
                 */
                public function resolve($object, array $args, $context, ResolveInfo $info)
                {
                    /* @var $submission TaskSubmission */
                    $submission = TaskSubmission::get()
                        ->filter(['UUID' => Convert::raw2sql($args['UUID'])])
                        ->first();

                    if (!$submission || !$submission->exists()) {
                        throw new Exception('Task submission with the given UUID cannot be found');
                    }

                    // Component Selection Tasks with a "Local" ComponentTarget
                    // do not "go to JIRA"...
                    $ticketId = Convert::raw2sql($args['JiraKey'] ?? '');

                    $isRemoteTarget = $submission->Task()->isRemoteTarget();

                    // check if taget is remote
                    if ($isRemoteTarget) {
                        // check for the empty ticket
                        if (empty($ticketId)) {
                            throw new Exception('Please enter a Project Key.');
                        }

                        // Do not permit the modification of a submission with the creation
                        // of a new ticket, if a different project key is passed-in.
                        if ($submission->JiraKey && $submission->JiraKey !== $ticketId) {
                            throw new Exception(sprintf('Project key must be the same as: %s', $submission->JiraKey));
                        }
                    }

                    $selectedComponents = json_decode(base64_decode($args['Components']), true);
                    $existingComponents = $submission->SelectedComponents();

                    /** Prevent multiple ticket creation */
                    $newTicketComponents = TaskSubmission::get_component_diff(
                        $selectedComponents,
                        $existingComponents->toNestedArray(),
                        'add'
                    );

                    $removedComponentdetails = TaskSubmission::get_component_diff(
                        $existingComponents->toNestedArray(),
                        $selectedComponents,
                        'remove'
                    );

                    // remove the component
                    foreach ($removedComponentdetails as $removedComponent) {
                        $filterArray = [
                            'SecurityComponentID' => $removedComponent['SecurityComponentID']
                        ];

                        if (!empty($removedComponent['ProductAspect'])) {
                            $filterArray = [
                                'SecurityComponentID' => $removedComponent['SecurityComponentID'],
                                'ProductAspect' => $removedComponent['ProductAspect']
                            ];
                        }

                        $existingComponent = $existingComponents->filter($filterArray)->first();

                        if ($existingComponent) {
                            $existingComponent->delete();
                        }
                    }

                    $createJiraTicket = !empty($ticketId) && $isRemoteTarget;

                    // add the component
                    foreach ($newTicketComponents as $newTicketComponent) {
                        $securityComponent = SecurityComponent::get_by_id(
                            Convert::raw2sql($newTicketComponent['SecurityComponentID'])
                        );

                        if ($securityComponent) {
                            $jiraLink = '';
                            if ($createJiraTicket) {
                                $jiraLink = $submission->issueTrackerService->addTask(// <-- Makes an API call
                                    $ticketId,
                                    $securityComponent,
                                    'Task',
                                    $newTicketComponent['ProductAspect']
                                );
                            }

                            $newComp = SelectedComponent::create();
                            $newComp->ProductAspect = $newTicketComponent['ProductAspect'];
                            $newComp->SecurityComponentID = $newTicketComponent['SecurityComponentID'];
                            $newComp->TaskSubmissionID = $submission->ID;
                            $newComp->write();

                            // crete ticket
                            if ($createJiraTicket) {
                                // create a new ticket for the selected component
                                $jiraTicket = JiraTicket::create();
                                $jiraTicket->JiraKey = $ticketId;
                                $jiraTicket->TicketLink = $jiraLink;
                                $jiraTicket->SecurityComponentID = $newComp->SecurityComponentID;
                                $jiraTicket->TaskSubmissionID = $newComp->TaskSubmissionID;
                                $jiraTicket->TaskSubmissionSelectedComponentID = $newComp->ID;
                                $jiraTicket->write();
                            }
                        }
                    }

                    // save JIRA project key for the task submission
                    if ($createJiraTicket && !empty($newTicketComponents)) {
                        $submission->JiraKey = $ticketId;
                        $submission->write();
                    }

                    return $submission;
                }
            })
            ->end();
    }

    /**
     * get component different for remove and add component
     *
     * @param array  $primaryArray   array 1
     * @param array  $secondaryArray array 2
     * @param string $type           add/remove
     * @return array
     */
    public static function get_component_diff(array $primaryArray, array $secondaryArray, string $type) : array
    {
        $returnArray = [];

        if (empty($primaryArray)) {
            return $returnArray;
        }

        foreach ($primaryArray as $primaryComponent) {
            $doesComponentExist = array_filter(
                $secondaryArray,
                function ($secondaryComponent) use ($primaryComponent, $type) {
                    $primaryProductAspect = isset($primaryComponent['ProductAspect']) ?
                        $primaryComponent['ProductAspect']: '';
                    $secondaryProductAspect = isset($secondaryComponent['ProductAspect']) ?
                        $secondaryComponent['ProductAspect']: '';

                    if (empty($primaryProductAspect) && empty($secondaryProductAspect)) {
                        return (
                            (int)$secondaryComponent['SecurityComponentID']
                            ===
                            (int)$primaryComponent['SecurityComponentID']
                        );
                    }

                    if (!empty($primaryProductAspect) && $type === 'add' && empty($secondaryProductAspect)) {
                        return [];
                    }

                    if (empty($primaryProductAspect) && $type === 'remove' && !empty($secondaryProductAspect)) {
                        return [];
                    }

                    if (!empty($primaryProductAspect) && !empty($secondaryProductAspect)) {
                        return (
                            (
                                (int)$secondaryComponent['SecurityComponentID']
                                ===
                                (int)$primaryComponent['SecurityComponentID']
                            ) &&
                            (string)$secondaryProductAspect === (string)$primaryProductAspect
                        );
                    }
                }
            );

            if (empty($doesComponentExist)) {
                $returnArray[] = [
                    'SecurityComponentID' => $primaryComponent['SecurityComponentID'],
                    'ProductAspect' => isset($primaryComponent['ProductAspect'])
                        ? $primaryComponent['ProductAspect'] : '' ,
                ];
            }
        }

        return $returnArray;
    }

    /**
     * Event handler called after writing to the database.
     *
     * @return void
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        $changed = $this->getChangedFields(['Status'], 1);

        // if task submission status is chnaged from backend (admin panel)
        // then updathe the QuestionnaireStatus to 'submitted'
        if (array_key_exists('Status', $changed) &&
            in_array($changed['Status']['before'], ['complete', 'approved']) &&
            $changed['Status']['after'] == 'in_progress') {
            $this->QuestionnaireSubmission()->QuestionnaireStatus = 'submitted';
            $this->QuestionnaireSubmission()->write();
        }
    }

    /**
     * send emails to the stakeholder group
     * @return void
     */
    public function sendEmailToStakeholder() : void
    {
        if ($this->Task()->IsStakeholdersSelected == 'Yes' && !$this->IsStakeholdersEmailSent) {
            if (!$this->Task()->StakeholdersGroup()->exists()) {
                throw new Exception('Sorry, no stakeholders group exist.');
            }

            // When we use $members = $this->Task()->StakeholdersGroup()->Members();
            // we get an error "Error: Cannot serialize Symfony\Component\Cache\Simple\PhpFilesCache".
            // So we get $members in the following way to solve the error:
            $members = Group::get()->filter(
                'code',
                $this->Task()->StakeholdersGroup()->Code
                )
                ->first()
                ->Members();

            if ($members && $members->Count()) {
                $this->IsStakeholdersEmailSent = 1;
                $queuedJobService = QueuedJobService::create();
                $queuedJobService->queueJob(
                    new SendTaskStakeholdersEmailJob($this, $members),
                    date('Y-m-d H:i:s', time() + 30)
                );
            }
        }
    }

    /**
     * Check if task approver is required
     * first check this on task level and then on action answer level
     *
     * @return boolean
     */
    public function getIsTaskApprovalRequired()
    {
        if (!$this->ApprovalGroup()->exists()) {
            return false;
        }

        if ($this->IsApprovalRequired) {
            return true;
        }

        if ($this->QuestionnaireData && $this->AnswerData) {
            $questionnaireDataObj = json_decode($this->QuestionnaireData);
            $answerDataObj = json_decode($this->AnswerData);

            $actionIdsforApproval = [];

            foreach ($questionnaireDataObj as $obj) {
                if ($obj->AnswerFieldType == 'action') {
                    foreach ($obj->AnswerActionFields as $answerActionField) {
                        //skip if this AAF is falsey for any reason
                        if (!$answerActionField) {
                            continue;
                        }

                        $approvalForTaskRequired = false;
                        if (isset($answerActionField->IsApprovalForTaskRequired)) {
                            $approvalForTaskRequired = (bool) $answerActionField->IsApprovalForTaskRequired;
                        }

                        if ($approvalForTaskRequired) {
                              $actionIdsforApproval[] = $answerActionField->ID;
                        }
                    }
                }
            }

            if (empty($actionIdsforApproval)) {
                return false;
            }

            foreach ($answerDataObj as $obj) {
                if ($obj->answerType) {
                    foreach ($obj->actions as $action) {
                        if ($action->isChose && in_array($action->id, $actionIdsforApproval)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if current user has access to approve and denied
     *
     * @return boolean
     */
    public function getIsCurrentUserAnApprover()
    {
        $member = Security::getCurrentUser();

        if (!$member) {
            return false;
        }

        if (!$member->groups()->exists()) {
            return false;
        }

        $groupIds = $member->groups()->column('ID');

        if (in_array($this->ApprovalGroup()->ID, $groupIds)) {
            return true;
        }

        return false;
    }

     /**
      * Not able to access members directly using relationship ($this->ApprovalGroup()->Members()),
      * getting the below error
      * (Cannot serialize Symfony\Component\Cache\Simple\Php File Cache in graphql)
      * that's why I need this function
      *
      * @throws Exception
      * @return DataList
      */
    public function approvalGroupMembers()
    {
        if (!$this->ApprovalGroup()->exists()) {
            throw new Exception('Sorry, no approval group exist.');
        }

        $group = Group::get()->filter('code', $this->ApprovalGroup()->Code)->first();

        return $group->Members();
    }

    /**
     * @param string $string     string
     * @param string $linkPrefix prefix before the link
     * @return string
     */
    public function replaceVariable($string = '', $linkPrefix = '')
    {
        $taskName = $this->Task()->Name;
        $SubmitterName = $this->Submitter()->Name;
        $SubmitterEmail = $this->Submitter()->Email;
        $productName = $this->QuestionnaireSubmission()->ProductName;
        $finalLink = '';

        if ($linkPrefix) {
            $links = $this->AnonymousAccessLink($linkPrefix);
        } else {
            $links = $this->SecureLink();
        }

        foreach ($links as $key => $link) {
            $finalLink .= '<br> Link for ' . $key . ': ' . $link;
        }

        $finalLink .=  '<br>';

        $string = str_replace('{$taskName}', $taskName, $string);
        $string = str_replace('{$taskLink}', $finalLink, $string);
        $string = str_replace('{$submitterName}', $SubmitterName, $string);
        $string = str_replace('{$submitterEmail}', $SubmitterEmail, $string);
        $string = str_replace('{$productName}', $productName, $string);

        return $string;
    }

    /**
     * @param string $component selectedProductAspect
     *
     * @return string
     */
    public function getRiskResultBasedOnAnswer($component = '', $statusForComponent = '')
    {
        // Deal with the related Questionnaire's Task-calcs, and append them
        $finalRiskResult = [];
        $newRiskResult = [];

        if ($statusForComponent == "complete" ||
            !in_array($this->Status, ["start", "in_progress", "invalid"])) {
            $newRiskResult = $this->getRiskResult('t', $component);
        }

        // if component exist then get and update the risk result for component
        if ($component) {
            $finalRiskResult = $this->getRiskResultForComponent($component, $newRiskResult);
        } else {
            // if product aspect doesn't exist at all for the submissions
            // then return the newRiskResult for the FinalRiskResult
            $finalRiskResult = $newRiskResult;
        }

        return json_encode($finalRiskResult);
    }

    /**
     * Add and update the new risk result data for the component
     *
     * @param string $component                 selected Product Aspect(component)
     * @param array  $newRiskResultForComponent risk result for component
     *
     * @return string
     */
    public Function getRiskResultForComponent($component, $newRiskResultForComponent)
    {
        $finalRiskResult = [];
        // Add risk result for first Component: if existing RiskResultData
        // is empty means we are writing risk result first time for the component
        // so add the risk result for component
        if(!$this->RiskResultData || $this->RiskResultData == '[]') {
            $temp['productAspect'] = $component;
            $temp['riskResult'] = $newRiskResultForComponent;
            $finalRiskResult[] = $temp;
        }
        else {
            // if existing RiskResultData is not empty then check all the below conditions
            $allRiskResult = json_decode($this->RiskResultData, true);
            $allProductAspectsForSubmission = json_decode($this->getProductAspects());
            $productAspectHasRiskResult = [];

            foreach ($allRiskResult as $key => $riskResultForComponent) {
                $productAspectHasRiskResult[] = $riskResultForComponent['productAspect'];
                // Update: if risk result already existst for the component then
                // update the old risk result with new risk result
                if($riskResultForComponent['productAspect'] == $component) {
                    $allRiskResult[$key]['riskResult'] = $newRiskResultForComponent;
                }
            }

            // if diff exists, means riskresult doesn't exist for the component
            $diffProductAspectArray = array_diff(
                $allProductAspectsForSubmission,
                $productAspectHasRiskResult
            );

            // Add in the existing result array: if risk result doesn't exist
            // for the componet then add the risk result for the component in
            // the existing RiskResultData
            if (!empty($diffProductAspectArray)) {
                $temp['productAspect'] = $component;
                $temp['riskResult'] = $newRiskResultForComponent;
                $allRiskResult[] = $temp;
            }

            $finalRiskResult = $allRiskResult;
        }

        return $finalRiskResult;
    }

    /**
     * Get all sibling task submissions from the parent
     * This list will include the current task submission
     *
     * @return DataList | null
     */
    public function getSiblingTaskSubmissions()
    {
        $qs = $this->QuestionnaireSubmission();

        if ($qs && $qs->exists()) {
            return $qs->TaskSubmissions();
        }

        return null;
    }

    /**
     * Get sibling task submissions by type from the parent
     * This list will include the current task submission
     *
     * @param string $type task type
     *
     * @return DataList | null
     */
    public function getSiblingTaskSubmissionsByType($type)
    {
        $siblingTasks = $this->getSiblingTaskSubmissions();

        if ($siblingTasks && $siblingTasks->Count() && ($taskByType = $siblingTasks->find('Task.TaskType', $type))) {
            return $taskByType;
        }

        return null;
    }

    /**
     * Get sibling task submissions status by type
     *
     * @param Dataonject $siblingTask sibling task
     * @return bool
     */
    public function isSiblingTaskCompleted($siblingTask) : bool
    {
        if ($siblingTask) {
            return ($siblingTask->Status === self::STATUS_COMPLETE ||
            $siblingTask->Status === self::STATUS_APPROVED);
        }

        return false;
    }

    /**
     * Find a risk assessment questionnaire task amongst the siblings of this
     * task submission, and return its risk result data.
     *
     * For reasons that escape the developer, GraphQL will not return this
     * TaskSubmission object as an object. It's cast to a string instead.
     * It will not return anything at all unless this specific name is used.
     * Thus, we use this getter to get the submission, and return an actual
     * string of data that we need (the RiskResultData, in this case)
     *
     * If you think you could use an alternative getter method here, like
     * getRiskResultDataFromTaskSubmission and call this getter, that doesn't
     * work either.
     *
     * @return string it's always a string, even if you want an object. it might
     *                also be null, if there aren't any other siblings
     */
    public function getRiskAssessmentTaskSubmission()
    {
        $siblings = $this->getSiblingTaskSubmissions();
        if ($siblings && $siblings->Count()) {
            $task = $siblings->find('Task.TaskType', 'risk questionnaire');

            //we should actually return a task here, but GraphQL has other ideas
            //returning the object will cast it to a string, so grab something
            //useful and return that instead.
            return $task->RiskResultData;
        }

        return null;
    }

    /**
     * @param DataObject $siblingTask sibling component selection task
     *
     * @return string
     */
    public function getDataforCVATask($siblingTask) : string
    {
        $selectedComponent = [];

        // if there is no sibling component selection task, then return default component od CVA task
        if (empty($siblingTask)) {
            return json_encode($this->getDefaultComponentsFromCVATask());
        }

        $isSiblingTaskCompleted = $this->isSiblingTaskCompleted($siblingTask);

        // if sibling component selection task exist and component target is "Local"
        if ($isSiblingTaskCompleted && $siblingTask->ComponentTarget == "Local") {
            $selectedComponent = $this->getSelectedComponentForLocal($siblingTask);
        }

        if ($isSiblingTaskCompleted && $siblingTask->ComponentTarget == "JIRA Cloud") {
            $selectedComponent = $this->getSelectedComponentForJiraCloud($siblingTask);
        }

        return json_encode($selectedComponent);
    }

    /**
     * get the selected component from the "component selection" task
     * when target type is "Local".
     *
     * @param DataObject $componentSelectionTask component selection task
     *
     * @return array
     */
    public function getSelectedComponentForLocal($componentSelectionTask) : array
    {
        $out = [];

        if (!$componentSelectionTask) {
            return $out;
        }

        $selectedComponents = $componentSelectionTask->SelectedComponents();

        if (!$selectedComponents) {
            return $out;
        }

        foreach ($selectedComponents as $comp) {
            $controls = [];

            if (!$comp->SecurityComponentID) {
                continue;
            }

            $controls = $comp->SecurityComponent()->Controls();

            $cvaControls = $this->getLocalAndDefaultCVAControls($controls, $comp->SecurityComponentID);

            $out[] = [
                'id' => $comp->SecurityComponent()->ID,
                'name' => $comp->SecurityComponent()->Name,
                'productAspect' => $comp->ProductAspect,
                'controls' => $cvaControls
            ];
        }

        return $out;
    }

    /**
     * @param DataObject $controls controls
     *
     * @return array
     */
    public function getLocalAndDefaultCVAControls($controls, $componentID)
    {
        $cvaControls = [];

        foreach ($controls as $ctrl) {
            $controlWeightSets = $ctrl->ControlWeightSets()->filter([
                'SecurityComponentID' => $componentID
            ]);

            $controlOwnerDetails = [];
            $riskCategories = [];
            $isKeyControl = false;

            foreach ($controlWeightSets as $controlWeightSet) {
                if ($risk = $controlWeightSet->Risk()) {
                    $riskCategories[] = [
                        'id' => $risk->ID,
                        'name' => $risk->Name,
                    ];

                    if ($controlWeightSet->LikelihoodPenalty > 0 ||
                        $controlWeightSet->ImpactPenalty > 0) {
                        $isKeyControl = true;
                    }
                }
            }

            $controlOwnerDetails[] = [
                'name' => $ctrl->ControlOwnerName,
                'email' => $ctrl->ControlOwnerEmailAddress,
                'team' => $ctrl->ControlOwnerTeam
            ];

            $cvaControls[] = [
                'id' => $ctrl->ID,
                'name' => $ctrl->Name,
                'description' => $ctrl->Description,
                'implementationGuidance' => $ctrl->ImplementationGuidance,
                'implementationEvidence'  => $ctrl->ImplementationEvidence,
                'selectedOption' => SecurityControl::CTL_STATUS_2,
                'implementationEvidenceUserInput' => '',
                'auditMethodUserInput' => '',
                'auditNotesAndFindingsUserInput' => '',
                'auditRecommendationsUserInput' => '',
                'riskCategories' => $riskCategories,
                'evalutionRating' => SecurityControl::EVALUTION_RATING_1,
                'isKeyControl' => $isKeyControl,
                'controlOwnerDetails' => $controlOwnerDetails,
                'implementationEvidenceHelpText' => $ctrl->ImplementationEvidenceHelpText,
                'implementationAuditHelpText' => $ctrl->ImplementationAuditHelpText
            ];
        }

        return $cvaControls;
    }

    /**
     * When no component selection task is available, we show default components
     * from the CVA task amongst the siblings of this task submission. These
     * default components are configured on the CVA task itself
     *
     * @return array
     */
    public function getDefaultComponentsFromCVATask() : array
    {
        $out = [];

        if ($this->TaskType !== 'control validation audit') {
            return $out;
        }

        $selectedComponents = $this->Task()->DefaultSecurityComponents();

        if (!$selectedComponents) {
            return $out;
        }

        $productAspects = json_decode($this->getProductAspects());

        if (!empty($productAspects)) {
            foreach ($productAspects as $productAspect) {
                foreach ($selectedComponents as $comp) {
                    $controls = $comp->Controls();

                    $cvaControls = $this->getLocalAndDefaultCVAControls($controls, $comp->ID);

                    $out[] = [
                        'id' => $comp->ID,
                        'name' => $comp->Name,
                        'productAspect' => $productAspect,
                        'controls' => $cvaControls
                    ];
                }
            }
        } else {
            foreach ($selectedComponents as $comp) {
                $controls = $comp->Controls();

                $cvaControls = $this->getLocalAndDefaultCVAControls($controls, $comp->ID);

                $out[] = [
                    'id' => $comp->ID,
                    'name' => $comp->Name,
                    'productAspect' => '',
                    'controls' => $cvaControls
                ];
            }
        }

        return $out;
    }

    /**
     * get the selected component from the "component selection" task
     * when target type is "JIRA Cloud"
     *
     * @param DataObject $componentSelectionTask component selection task
     *
     * @return array
     */
    public function getSelectedComponentForJiraCloud($componentSelectionTask) : array
    {
        $out = [];

        if (!$componentSelectionTask) {
            return $out;
        }

        $selectedComponents = $componentSelectionTask->SelectedComponents();

        if (!$selectedComponents) {
            return $out;
        }

        foreach ($selectedComponents as $selectedComponent) {
            $securityComponent = $selectedComponent->SecurityComponent();

            if (!$securityComponent) {
                continue;
            }

            $controls = [];
            // get JiraTicket details
            $ticket = JiraTicket::get()
              ->filter([
                  'TaskSubmissionID' => $selectedComponent->TaskSubmissionID,
                  'SecurityComponentID' => $selectedComponent->SecurityComponentID,
                  'TaskSubmissionSelectedComponentID' => $selectedComponent->ID
              ])->first();

            if (($localControls = $securityComponent->Controls()) && $ticket) {
                $remoteControls =
                    $componentSelectionTask->issueTrackerService->getControlDetailsFromJiraTicket($ticket) ?: [];

                foreach ($localControls as $localControl) {
                    $doesControlExist = [];
                    $doesControlExist = array_filter($remoteControls, function ($remoteControl) use ($localControl) {
                        return (int)$remoteControl['ID'] === (int)$localControl->ID;
                    });

                    if (!empty($remoteControl = array_pop($doesControlExist))) {
                        $controls[] = [
                            'id' => $localControl->ID,
                            'name' => $localControl->Name,
                            'selectedOption' => $remoteControl['SelectedOption']
                        ];
                    }
                }
            }

            $out[] = [
                'id' => $securityComponent->ID,
                'name' => $securityComponent->Name,
                'productAspect' => $selectedComponent->ProductAspect,
                'jiraTicketLink' => $ticket ? $ticket->TicketLink : '',
                'controls' => $controls
            ];
        }

        return $out;
    }

    /**
     * Get the current hostname or an alternate one from the SiteConfig
     *
     * @return string
     */
    public function getHostname() : string
    {
        $hostname = Director::absoluteBaseURL();
        $config = SiteConfig::current_site_config();
        if ($config->AlternateHostnameForEmail) {
            $hostname = $config->AlternateHostnameForEmail;
        }

        return $hostname;
    }

    /**
     * Update CMS Fields specific to the control validation audit task
     * submission. At some point this should be moved into the getCMSFields
     * method of a separate subclass of Task
     *
     * @param [type] $fields FieldList obtained from getCMSFields
     * @return FieldList a modified version of $fields, passed in via parameter
     */
    public function getCVA_CMSFields($fields)
    {
        $fields->removeByName(
            [
                'QuestionData',
                'AnswerData',
                'QuestionnaireDataToggle',
                'AnswerDataToggle',
                'CVATaskData',
                'EmailRelativeLinkToTask',
                'JiraKey',
                'JiraTickets',
                'SelectedComponents',
                'ResultToggle',
                'Result'
            ]
        );
        $fields->addFieldToTab(
            'Root.TaskSubmissionData',
            ToggleCompositeField::create(
                'CVATaskDataToggle',
                'CVA Task Data',
                [
                    TextareaField::create('CVATaskData')
                ]
            )
        );
        return $fields;
    }

    /**
     * Get is logged in user collborator
     * @return bool
     */
    public function getIsTaskCollborator() : bool
    {
        $member = Security::getCurrentUser();

        if (!$member || $member == null) {
            return false;
        }

        $collboratorIDs = $this->QuestionnaireSubmission()->Collaborators()->column('ID');

        if (empty($collboratorIDs)) {
            return false;
        }

        if (in_array($member->ID, $collboratorIDs)) {
            return true;
        }

        return false;
    }

    /**
     * Get is logged in user is approver
     * @return bool
     */
    public function getIsTaskApprover() : bool
    {
        $member = Security::getCurrentUser();

        if (!$member || $member == null || !$this->approvalGroupMembers()) {
            return false;
        }

        $approverIDs = $this->approvalGroupMembers()->column('ID');

        if (empty($approverIDs)) {
            return false;
        }

        if (in_array($member->ID, $approverIDs)) {
            return true;
        }

        return false;
    }

    /**
     * If all sibling tasks are completed or approved then send an email to notify submitter
     *
     * @return void
     */
    public function sendAllTheTasksCompletedEmail()
    {
        $siblingTasks = $this->getSiblingTaskSubmissions();
        $sendNotifyingEmail = true;

        if ($siblingTasks && $siblingTasks->Count()) {
            foreach ($siblingTasks as $siblingTask) {
                if ($this->isSiblingTaskCompleted($siblingTask) == false) {
                    $sendNotifyingEmail = false;
                    break;
                }
            }
        }

        if ($sendNotifyingEmail && !$this->QuestionnaireSubmission()->IsAllTheTasksCompletedEmailSent) {
            $questionnaireSubmission = $this->QuestionnaireSubmission();
            $questionnaireSubmission->IsAllTheTasksCompletedEmailSent = 1;
            $questionnaireSubmission->write();
            $qs = QueuedJobService::create();
            $qs->queueJob(
                new SendAllTheTasksCompletedEmailJob($this->QuestionnaireSubmission()),
                date('Y-m-d H:i:s', time() + 30)
            );
        }
    }

    /**
     * @return string
     */
    public function getTimetoComplete()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->TimeToComplete;
    }

    /**
     * @return string
     */
    public function getTimetoReview()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->TimeToReview;
    }

    /**
     * @return string
     */
    public function getPreventMessage()
    {
        $task = $this->Task();

        if (!$task->exists()) {
            return "";
        }

        return $task->PreventMessage;
    }

    /**
     * @return boolean
     */
    public function getCanTaskCreateNewTasks()
    {
        return $this->Task()->getCanTaskCreateNewTasks();
    }

    /**
     * return risk profile data for question 4 for C&A memo task
     *
     * @return string
     */
    public function getRiskProfileData()
    {
        // check sibling sra tasks
        $siblingTaskSubmissions = $this->getSiblingTaskSubmissions();
        $finalResult["message"] = "There are no digital security risk assessments to link against this Certification and Accreditation.";
        $finalResult["isDisplayMessage"] = true;
        $finalResult['hasProductAspects'] = false;

        if ($this->Task()->isCertificationAndAccreditationType() &&
            $siblingTaskSubmissions && $siblingTaskSubmissions->Count()) {

            foreach ($siblingTaskSubmissions as $taskSubmission) {

                if ($taskSubmission->Task()->isSRAType() &&
                    $taskSubmission->Status == self::STATUS_COMPLETE &&
                    $taskSubmission->AnswerData)
                {
                    $answerData = json_decode($taskSubmission->AnswerData, true);
                    $finalResult["isDisplayMessage"] = false;
                    $riskresultDetails = [];

                    foreach ($answerData as $data) {
                        $productAspectName = $data["productAspect"];
                        $result = json_decode($data['result'], true);

                        foreach ($result as $riskDetails) {
                            $riskresult["riskId"] = $riskDetails["riskId"];
                            $riskresult["riskName"] = $riskDetails["riskName"];
                            $riskresult["currentRiskRating"] = $riskDetails["riskDetail"]["currentRiskRating"];

                            if (!empty($productAspectName)) {
                                $finalResult['hasProductAspects'] = true;
                                $riskresultDetails[$productAspectName][] = $riskresult;
                            } else {
                                $riskresultDetails[] = $riskresult;
                            }
                        }
                    }

                    $finalResult["result"] = $riskresultDetails;
                }
            }
        }

        return json_encode($finalResult);
    }

    /**
     * For C&A memo task return the result for information classification task result
     *
     * @return string
     */
   public function getInformationClassificationTaskResult()
   {
       $result = '';
       $siblingTasks = $this->getSiblingTaskSubmissions();

       if ($siblingTasks && $siblingTasks->Count()) {
           foreach ($siblingTasks as $task) {
               if ($task->Task()->ID === $this->Task()->InformationClassificationTask()->ID &&
                   ($task->Status == self::STATUS_COMPLETE || $task->Status == self::STATUS_APPROVED) &&
                   $task->Result) {
                   $resultArray = explode(":", $task->Result);
                   $taskResult = isset($resultArray[1]) ?
                       trim($resultArray[1]): trim($resultArray[0]);

                   $optionArray = [
                       'unclassified',
                       'in-confidence',
                       'sensitive',
                       'restricted',
                       'confidential',
                       'secret',
                       'top-secret'
                   ];

                   if ($taskResult && in_array(strtolower($taskResult), $optionArray)) {
                       $result = json_encode(['value' => strtolower($taskResult), 'label' => $taskResult]);
                   }
               }
           }
       }

       return $result;
    }

    /**
     * calculate the result of c&a memo task
     *
     * @return array
     */
    public function finalResultForCertificationAndAccreditation()
    {
        if (!$this->Task()->isCertificationAndAccreditationType()) {
            return "[]";
        }

        // get questions and answers from submission json
        $questionnaireData = json_decode($this->QuestionnaireData, true);
        $answerData = json_decode($this->AnswerData, true);
        $questionnaireSubmission = $this->QuestionnaireSubmission();
        $member = Security::getCurrentUser();
        $siteConfig = SiteConfig::current_site_config();
        $result = [];

        if (empty($questionnaireData) || empty($answerData) || empty($questionnaireSubmission)) {
            return "[]";
        }

        $result['organisationName'] = $siteConfig->OrganisationName;
        $result['reportLogo'] = $siteConfig->CertificationAndAccreditationReportLogo()->Link();
        $result['productName'] = $questionnaireSubmission->ProductName;
        $result['businessOwnerName'] = $questionnaireSubmission->getBusinessOwnerApproverName();
        $result['securityArchitectName'] = implode(' ', [$member->FirstName, $member->Surname]);
        $result['createdAt'] = date("Y/m/d");
        $result['riskProfileData'] = $this->RiskProfileData;

        // get all task and exclude current task (C&A memo task)
        $taskSubmissions = $questionnaireSubmission->TaskSubmissions()->exclude('ID', $this->ID);

        foreach ($taskSubmissions as $taskSubmission) {
            $taskApprover = $taskSubmission->TaskApprover();
            $taskApproverName = $taskApprover->exists() ?
                (implode(' ', [$taskApprover->FirstName, $taskApprover->Surname])) : '';

            $data['taskName'] = $taskSubmission->TaskName;
            $data['taskApproverName'] = $taskApproverName;
            $data['taskStatus'] = $taskSubmission->Status;
            $result['tasks'][] = $data;

            if ($taskApprover->exists() && !empty($taskSubmission->TaskRecommendationData)) {
                $data['taskRecommendationData'] = $taskSubmission->TaskRecommendationData;
                $result['taskRecommendations'][] = $data;
            }
        }

        if (!isset($result['taskRecommendations'])) {
            $result['taskRecommendations'] = [];
        }

        // traverse questions to get result from c&a memo task
        foreach ($questionnaireData as $question) {
            $questionID = $question['ID'];

            // get answers for all the input fields of the questions
            if ($questionID && isset($answerData[$questionID]) && !$answers = $answerData[$questionID]) {
                continue;
            }

            // if question type is input
            if ($question['AnswerFieldType'] === 'input' && !empty($question['AnswerInputFields'])) {
                foreach ($question['AnswerInputFields'] as $inputField) {
                    switch ($inputField['CertificationAndAccreditationInputType']) {
                        case "product description":
                            $result["productDescription"] = $this->getAnswer($inputField, $answers);
                            break;
                        case "service name":
                            $result["serviceName"] = $this->getLabel($this->getAnswer($inputField, $answers));
                            break;
                        case "classification level":
                            $result["classificationLevel"] = $this->getLabel($this->getAnswer($inputField, $answers));
                            break;
                        case "accreditation level":
                            $result["accreditationLevel"] = $this->getAnswer($inputField, $answers);
                            break;
                        case "accreditation description":
                            $result["accreditationDescription"] = $this->getAnswer($inputField, $answers);
                            break;
                        case "accreditation type":
                            $result["accreditationType"] = $this->getLabel($this->getAnswer($inputField, $answers));
                            break;
                        case "accreditation period":
                            $result["accreditationPeriod"] = $this->getLabel($this->getAnswer($inputField, $answers));
                            break;
                        case "accreditation renewal recommendations":
                            $result["accreditationRenewalRecommendations"] = $this->getAnswer($inputField, $answers);
                            break;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * get the result of c&a memo task
     *
     * @return string
     */
    public function getResultForCertificationAndAccreditation()
    {
        $result = $this->finalResultForCertificationAndAccreditation();
        return json_encode($result);
    }

    /**
     * get the answer of input field
     * @param array input   input field detail array
     * @param array answers answers of the question
     *
     * @return string
     */
    public function getAnswer($input, $answers)
    {
        $inputFieldID = $input['ID'];
        $inputFieldAnswer = [];

        if (!$answers || !isset($answers['inputs'])) {
            return;
        }

        $inputFieldAnswer = array_filter($answers['inputs'], function ($e) use ($inputFieldID) {
            return isset($e['id']) && $e['id'] == $inputFieldID;
        });

        // get answer array from $inputFieldAnswer
        if (empty($inputFieldAnswer)) {
            return;
        }

        $answer = array_pop($inputFieldAnswer);
        $data = $answer['data']; // string for radio

        return $data;
    }

    /**
     * return label for multi choice field (radio, dropdown and checkbox)
     *
     * @return string
     */
    public function getLabel($jsonObj)
    {
        $data = json_decode($jsonObj, true);
        return is_array($data)  && isset($data['label'])? $data['label'] : '';
    }

    /**
     * return label for multi choice field (radio, dropdown and checkbox)
     *
     * @return string
     */
    public function getValue($jsonObj)
    {
        $data = json_decode($jsonObj, true);
        return is_array($data) && isset($data['value'])? $data['value'] : '';
    }

    /**
     * check all condition to display prevent message if task type is C&A memo
     *
     * @return boolean
     */
    public function getIsDisplayPreventMessage()
    {
        $isDisplayPreventMessage = false;

        if (!$this->Task()->isCertificationAndAccreditationType()) {
            return $isDisplayPreventMessage;
        }

        if ($this->Status == self::STATUS_COMPLETE) {
            return $isDisplayPreventMessage;
        }

        $siblingTasks = $this->getSiblingTaskSubmissions();

        if (!$siblingTasks || !$siblingTasks->Count()) {
            return $isDisplayPreventMessage;
        }

        $siblings = $siblingTasks
            ->exclude(['ID' => $this->ID])
            ->filter(['Status:not' => self::STATUS_INVALID]);

        foreach ($siblings as $siblingTask) {
            if ($this->isSiblingTaskCompleted($siblingTask) == false) {
                $isDisplayPreventMessage = true;
                return $isDisplayPreventMessage;
            }
        }

        $accessGroup = $this->Task()->CertificationAndAccreditationGroup();

        if (!Member::currentUser()->inGroup($accessGroup->ID)) {
            return $isDisplayPreventMessage = true;
        }

        return $isDisplayPreventMessage;
    }

    /**
     * get data to create AccreditationMemo from the result of c&a memo task
     *
     * @return array
     */
    public function getDataForAccreditationMemo()
    {
        $questionnaireData = json_decode($this->QuestionnaireData, true);
        $answerData = json_decode($this->AnswerData, true);

        if (empty($questionnaireData) || empty($answerData)) {
            return;
        }

        // traverse questions to get result from c&a memo task
        foreach ($questionnaireData as $question) {
            $questionID = $question['ID'];

            // get answers for all the input fields of the questions
            if ($questionID && isset($answerData[$questionID]) && !$answers = $answerData[$questionID]) {
                continue;
            }

            // if question type is input
            if ($question['AnswerFieldType'] === 'input' && !empty($question['AnswerInputFields'])) {
                foreach ($question['AnswerInputFields'] as $inputField) {
                    switch ($inputField['CertificationAndAccreditationInputType']) {
                        case "service name":
                            $result["serviceID"] = $this->getValue($this->getAnswer($inputField, $answers));
                            break;
                        case "accreditation level":
                            $result["accreditationLevelValue"] = strtolower($this->getAnswer($inputField, $answers));
                            break;
                        case "accreditation period":
                            $result["accreditationPeriod"] = $this->getLabel($this->getAnswer($inputField, $answers));
                            break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param string $productAspect selected prodouct aspect
     * @param string $status        status
     * @param string $sraTask       sra task data
     * @return string
     */
    public function updateSecurityRiskAssessmentData($productAspect = '', $status = '', $sraTask = '') : string
    {
        if (empty($sraTask)) {
            return '';
        }

        $answerDataArray = json_decode($sraTask->AnswerData, true);
        $sraResult = $sraTask->calculateSecurityRiskAssessmentData($productAspect);

        $doesSraResultExist = false;
        if (!empty($answerDataArray) && !empty($sraTask->findSraResult($productAspect))) {
            foreach ($answerDataArray as $key =>$riskDetails) {
                if ($riskDetails['productAspect'] == $productAspect) {
                    $answerDataArray[$key]['status'] = $status;
                    $answerDataArray[$key]['result'] = $sraResult;
                    $doesSraResultExist = true;
                }
            }
        }

        if (empty($answerDataArray) || !$doesSraResultExist) {
            $data['productAspect'] = $productAspect;
            $data['status'] = $status;
            $data['result'] = $sraResult;
            $answerDataArray[] = $data;
        }

        // below two lines are just for testing purpose
        $sraTask->AnswerData = json_encode($answerDataArray);
        $sraTask->Status = $status;
        $sraTask->write();

        return $sraResult;
    }

    /**
     * @param string $productAspect selected prodouct aspect
     * @return string
     */
    public function completeTaskSubmission($component)
    {
        $allAnswerData = json_decode($this->AnswerData, true);
        $answerDataResultForSelectedComponent = Null;
        $taskStatusforProductAspect = [];

        if ($this->checkForMultiComponent($component)) {
            $doesSetTaskSubmissionStatusToComplete = true;

            if(!empty($allAnswerData)) {
                foreach ($allAnswerData as $key => $answerDataForComponent) {
                    if ($answerDataForComponent['productAspect'] == $component) {
                        $answerDataForComponent['status'] = TaskSubmission::STATUS_COMPLETE;
                        $allAnswerData[$key] = $answerDataForComponent;
                        $answerDataResultForSelectedComponent = $answerDataForComponent['result'];
                        $this->AnswerData = json_encode($allAnswerData);
                        $this->RiskResultData = $this->getRiskResultBasedOnAnswer($component, $answerDataForComponent['status']);
                    } else if ($answerDataForComponent['status'] !== TaskSubmission::STATUS_COMPLETE) {
                        $doesSetTaskSubmissionStatusToComplete = false;
                    }

                    $taskStatusforProductAspect[$answerDataForComponent['productAspect']] = $answerDataForComponent['status'];
                }

                if ($doesSetTaskSubmissionStatusToComplete) {
                    $allProductAspectsForSubmission = json_decode($this->getProductAspects());
                    $productAspectsExistInAnswerData = array_keys($taskStatusforProductAspect);
                    $result = array_diff($allProductAspectsForSubmission, $productAspectsExistInAnswerData);
                    if (empty($result)) {
                        $this->Status = TaskSubmission::STATUS_COMPLETE;
                    }
                }
            }
        } else {
            // if task is without component means save a singletask
            // and change the status to complete
            $this->Status = TaskSubmission::STATUS_COMPLETE;
            $this->RiskResultData = $this->getRiskResultBasedOnAnswer();
        }

        return $answerDataResultForSelectedComponent;
    }

    /**
     * @param string $productAspect selected prodouct aspect
     * @return string
     */
    public function getSraResult($productAspect) : string
    {
        $sraResult = $this->findSraResult($productAspect);

        // save the sra result first time and keep the status as start
        if (empty($sraResult)) {
            $sraResult = $this->updateSecurityRiskAssessmentData($productAspect, self::STATUS_START, $this);
        }

        return $sraResult;
    }

    /**
     * @param string $productAspect selected prodouct aspect
     * @return string
     */
    public function findSraResult($productAspect) : string
    {
        $answerDataArray = json_decode($this->AnswerData, true);

        if (!empty($answerDataArray)) {
            foreach ($answerDataArray as $key => $riskDetails) {
                if ($riskDetails['productAspect'] == $productAspect) {
                    return $riskDetails['result'];
                }
            }
        }

        return '';
    }

    /**
     * @param string $component selected prodouct aspect
     * @return string
     */
    public function calculateSecurityRiskAssessmentData($component = '') : string
    {
        if ($this->TaskType === 'security risk assessment') {
            $sraCalculator = SecurityRiskAssessmentCalculator::create(
                $this->QuestionnaireSubmission(),
                $component
            );

            return json_encode($sraCalculator->getSRATaskdetails($component));
        }

        return '';
    }

    /**
     * @param DataObject sraTask sra task submission
     *
     * @return array LikelihoodRatings
     */
    public function getLikelihoodRatingsThresholds($sraTask = '')
    {
        if (empty($sraTask)) {
            $sraTask = $this->task();
        }

        return json_encode($sraTask->getLikelihoodRatingsData());
    }

    /**
     * @param DataObject sraTask sra task submission
     *
     * @return array RiskRatings matrix
     */
    public function getRiskRatingThresholdsMatix($sraTask = '')
    {
        if (empty($sraTask)) {
            $sraTask = $this->task();
        }

        return json_encode($sraTask->getRiskRatingMatix());
    }

    /**
     * Add default cva data if selection task doesn't exist and set as complete
     *
     * @param DataObject questionnaireSubmission questionnaire submission
     */
    public static function findAndSetDefaultCvaTaskData($questionnaireSubmission = '')
    {
        // get cva task which is generated by questionnaire submissions
        // not from task submission
        $cvaTasksubmission = $questionnaireSubmission->TaskSubmissions()
            ->filter('Status', TaskSubmission::STATUS_START)
            ->find('Task.TaskType', 'control validation audit');

        // check if component selection task exists
        if ($cvaTasksubmission) {
            $isComponentSelectionExist = $cvaTasksubmission->getSiblingTaskSubmissionsByType('selection');
        }

        // set defult cva task data if component selection task does not exist
        // then change the status to complete
        if ($cvaTasksubmission && empty($isComponentSelectionExist)) {
            $cvaTasksubmission->CVATaskData = $cvaTasksubmission->getDataforCVATask(NULL);
            if (!empty(json_decode($cvaTasksubmission->CVATaskData))) {
                $cvaTasksubmission->Status = TaskSubmission::STATUS_COMPLETE;
                $cvaTasksubmission->write();
            }
        }
    }
}
