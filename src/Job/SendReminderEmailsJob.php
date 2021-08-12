<?php

/**
 * This file contains the "SendReminderEmailsJob" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2021 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Job;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use NZTA\SDLT\Model\QuestionnaireSubmission;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Injector\Injector;
use NZTA\SDLT\Email\SendApprovalLinkEmail;

/**
 * A QueuedJob that sends reminder approval link emails to bo and ciso if approvals are pending
 */
class SendReminderEmailsJob extends AbstractQueuedJob implements QueuedJob
{
    /**
     * @param QuestionnaireSubmissionID $questionnaireSubmission A questionnaireSubmission record
     * @param ManyManyList              $members                 A list of {@link Member} records
     * @param string                    $businessOwnerEmail      A business owner email address
     * @param integer                   reminderEmailInDays      number of days to send reminder email

     *
     * @return void
     */
    public function __construct($questionnaireSubmission = null, $members = null, $businessOwnerEmail = '', $reminderEmailInDays = 0)
    {
        $this->questionnaireSubmission = $questionnaireSubmission;
        $this->cisoMembers = $members;
        $this->businessOwnerEmail = $businessOwnerEmail;
        $this->reminderEmailInDays = $reminderEmailInDays;
    }

     /**
     * @return string
     */
    public function getTitle()
    {
        return sprintf(
            'Check awaiting BO and awaiting CISO approvals for - %s (%d)',
            $this->questionnaireSubmission->Questionnaire()->Name,
            $this->questionnaireSubmission->ID
        );
    }

    /**
     * {@inheritDoc}
     * @return string
     */
    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    /**
     * @return void
     */
    public function process()
    {
        // get the updated value of the questionnaire submission to check the current status
        $this->questionnaireSubmission = QuestionnaireSubmission::get_by_id($this->questionnaireSubmission->ID);

        if ($this->questionnaireSubmission->isCisoApprovalPending()) {
            new SendApprovalLinkEmail($this->questionnaireSubmission, $this->cisoMembers);
        }

        if ($this->questionnaireSubmission->isBOApprovalPending()) {
            new SendApprovalLinkEmail($this->questionnaireSubmission, [], $this->businessOwnerEmail);
        }

        // create a reminder job again only if ciso or bo status is pending
        if ($this->questionnaireSubmission->isCisoApprovalPending() ||
            $this->questionnaireSubmission->isBOApprovalPending()) {
            $nextJob = Injector::inst()->create(
                SendReminderEmailsJob::class,
                $this->questionnaireSubmission,
                $this->cisoMembers,
                $this->businessOwnerEmail,
                $this->reminderEmailInDays
            );
            // create a queue job again
            $queuedJobService = QueuedJobService::create();
            $queuedJobService->queueJob(
                $nextJob,
                date('Y-m-d H:i:s', strtotime("+". $this->reminderEmailInDays . " day", time()))
            );
        }

        $this->isComplete = true;
    }
}
