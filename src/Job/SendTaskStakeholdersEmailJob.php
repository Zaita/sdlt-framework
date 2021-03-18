<?php

/**
 * This file contains the "SendTaskStakeholdersEmailJob" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2018 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Job;

use SilverStripe\Control\Email\Email;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Services\QueuedJob;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * A QueuedJob is specifically designed to be invoked from an onAfterWrite() process
 */
class SendTaskStakeholdersEmailJob extends AbstractQueuedJob implements QueuedJob
{
    /**
     * @param TaskSubmission $taskSubmission taskSubmission
     * @param Member         $members    member
     */
    public function __construct($taskSubmission = null, $members = [])
    {
        $this->taskSubmission = $taskSubmission;
        $this->members = $members;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return sprintf(
            'Initialising task stakeholders email for - %s (%d)',
            $this->taskSubmission->Task()->Name,
            $this->taskSubmission->ID
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
     * @return mixed void | null
     */
    public function process()
    {
        foreach ($this->members as $member) {
            $this->sendEmail($member->FirstName, $member->Email);
        }

        $this->isComplete = true;
    }

    /**
     * @param string $name    name
     * @param string $toEmail to Email
     *
     * @return null
     */
    public function sendEmail($name = '', $toEmail = '')
    {
        $emailDetails = SiteConfig::current_site_config()->TaskEmail();
        if ($emailDetails && $emailDetails->ID) {
            $sub = $this->taskSubmission->replaceVariable($emailDetails->StakeholdersEmailSubject);
            $from = $emailDetails->FromEmailAddress;

            $email = Email::create()
                ->setHTMLTemplate('Email\\EmailTemplate')
                ->setData([
                    'Name' => $name,
                    'Body' => $this->taskSubmission->replaceVariable($emailDetails->StakeholdersEmailBody, $emailDetails->LinkPrefix),
                    'EmailSignature' => $emailDetails->EmailSignature
                ])
                ->setFrom($from)
                ->setTo($toEmail)
                ->setSubject($sub);

            $email->send();
        }
    }
}
