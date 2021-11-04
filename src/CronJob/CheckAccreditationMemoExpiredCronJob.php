<?php

/**
 * This file contains the "CheckAccreditationMemoExpiredCronJob" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2021 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

use NZTA\SDLT\Model\AccreditationMemo;
use SilverStripe\CronTask\Interfaces\CronTask;

/**
 * A cron job that checks for expired accreditation memos everyday at midnight
 */
class CheckAccreditationMemoExpiredCronJob implements CronTask
{
    /**
     * Run the job everyday at midnight
     *
     * @return string
     */
    public function getSchedule()
    {
        return "0 0 * * *";
    }

    /**
     *
     * @return void
     */
    public function process()
    {
        echo "Checking for expired Accreditation Memos...\n";

        $accreditationMemos = AccreditationMemo::get()
            ->filter([
                "ExpirationDate:LessThan" => date('Y-m-d')
            ]);

        foreach ($accreditationMemos as $accreditationMemo) {
            //Check the expiration date and update accreditation status
            if ($accreditationMemo->AccreditationStatus == "active") {
                echo "Update the status for ID " . $accreditationMemo->ID . "\n";
                $accreditationMemo->AccreditationStatus = "expired";
                $accreditationMemo->write();
            }
        }

        echo "The accreditation status of all expired memos has been successfully updated.\n";
    }
}
