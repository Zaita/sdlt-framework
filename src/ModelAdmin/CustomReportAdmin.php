<?php

/**
 *This file contains the "CustomReportAdmin" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2021 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\ModelAdmin;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Reports\Report;
use SilverStripe\Reports\ReportAdmin;

/**
 * Class CustomReportAdmin
 *
 */
class CustomReportAdmin extends ReportAdmin
{
    /**
     * This function overrides the default Reports() to accomodate hiding a specific report
     * @return ArrayList $output list of reports to show
     */
    public function Reports()
    {
        $completedPenTestReport = Injector::inst()->create('NZTA\SDLT\Reports\SDLTReports_CompletedPenetrationTests');
        $output = new ArrayList();

        foreach (Report::get_reports() as $report) {
            //if the penetration task does not exist, hide CompletedPenetrationTests report
            if ((count($completedPenTestReport->sourceRecords()) == 0 &&
                !in_array(get_class($report), ['NZTA\SDLT\Reports\SDLTReports_CompletedPenetrationTests']))) {
                if ($report->canView()) {
                    $output->push($report);
                }
            }
            //else if the penetration task does exist, include CompletedPenetrationTests report
            elseif ((count($completedPenTestReport->sourceRecords()) > 1 && $report->canView())) {
                $output->push($report);
            }
        }

        return $output;
    }
}
