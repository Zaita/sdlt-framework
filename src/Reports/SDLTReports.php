<?php
/**
 * This file contains the "SDLTReports" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2021 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Reports;

use SilverStripe\Reports\Report;
use NZTA\SDLT\Model\QuestionnaireSubmission;
use NZTA\SDLT\Model\TaskSubmission;
use SilverStripe\ORM\ArrayList;
use DateTime;

/**
 * A custom report that generates the questionnaire submissions by pillar per year
 */
class SDLTReports_SubmissionsByYear extends Report
{
     /**
     * @return string
     */
    public function title()
    {
        return 'Submissions by pillar per year';
    }

     /**
     * @param mixed|null $params    
     * @return ArrayList
     */
    public function sourceRecords($params = null)
    {
        $submissions = QuestionnaireSubmission::get()->filter([
            'QuestionnaireStatus:Not' => ['expired', 'in_progress', 'denied'],
        ]);

        $grouped = [];
        foreach ($submissions as $submission) {
             $key = substr($submission->Created, 0, 4). '_' . $submission->Questionnaire()->Name;

            if (array_key_exists($key, $grouped)) {
                $grouped[$key]['Num']++;
            } else {
                $grouped[$key] = [
                    'Name' => $submission->Questionnaire()->Name,
                    'Year' => substr($submission->Created, 0, 4),
                    'Num' => 1,
                ];
            }
        }
        $year = array_column($grouped, 'Year');
        $name = array_column($grouped, 'Name');
        array_multisort($year, SORT_DESC, $name, SORT_ASC, $grouped);

        return ArrayList::create($grouped);
    }

    /**
     * @return string[] $fields
     */
    public function columns()
    {
        $fields = [
            'Name' => 'Pillar Name',
            'Year' => 'Created Date',
            'Num' => 'Submission Count',
        ];
        return $fields;
    }
}
/**
 * A custom report that generates the questionnaire submissions by pillar per month per year
 */
class SDLTReports_SubmissionsByMonth extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return 'Submissions by pillar per month';
    }

    /**
     * @param mixed|null $params      
     * @return ArrayList
     */
    public function sourceRecords($params = null)
    {
        $submissions = QuestionnaireSubmission::get()->filter([
            'QuestionnaireStatus:Not' => ['expired', 'in_progress', 'denied'],
        ]);

        $grouped = [];
        foreach ($submissions as $submission) {
             $key = substr($submission->Created, 0, 4). '_' . $submission->Questionnaire()->Name . substr($submission->Created, 5, 2);
            
             if (array_key_exists($key, $grouped)) {
                $grouped[$key]['Num']++;
            } else {
                $grouped[$key] = [
                    'Name' => $submission->Questionnaire()->Name,
                    'Year' => substr($submission->Created, 0, 4),
                    'Month' => substr($submission->Created, 5, 2),
                    'Num' => 1,
                ];
            }
        }
        $name = array_column($grouped, 'Name');
        $year = array_column($grouped, 'Year');
        $month = array_column($grouped, 'Month');
        array_multisort($name, SORT_ASC, $year, SORT_DESC, $month, SORT_DESC, $grouped);

        return ArrayList::create($grouped);
    }

    /**
     * @return string[] $fields
     */
    public function columns()
    {
        $fields = [
            'Name' => 'Pillar Name',
            'Year' => 'Created Year',
            'Month' => 'Created Month',
            'Num' => 'Submission Count',
        ];
        return $fields;
    }
}

/**
 * A custom report that generates the approvals by security architect per year
 */
class SDLTReports_ApprovalsBySAPerYear extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return 'Approvals by security architect per year';
    }

    /**
     * @param mixed|null $params     
     * @return ArrayList
     */
    public function sourceRecords($params = null)
    {
        $submissions = QuestionnaireSubmission::get()->filter([
            'SecurityArchitectApprovalStatus' => ['approved'],
        ]);

        $grouped = [];
        foreach ($submissions as $submission) {
            $key = $submission->SecurityArchitectApprover->FirstName . '_' . $submission->SecurityArchitectApprover->Surname.
                    substr($submission->Created, 0, 4);

            if (array_key_exists($key, $grouped)) {
                $grouped[$key]['Num']++;
            } else {
                $grouped[$key] = [
                    'FirstName' => $submission->SecurityArchitectApprover->FirstName,
                    'LastName' => $submission->SecurityArchitectApprover->Surname,
                    'Year' => substr($submission->Created, 0, 4),
                    'Num' => 1,
                ];
            }
        }
        $year = array_column($grouped, 'Year');
        $count = array_column($grouped, 'Num');
        array_multisort($year, SORT_DESC, $count, SORT_DESC, $grouped);

        return ArrayList::create($grouped);
    }

    /**
     * @return string[] $fields
     */
    public function columns()
    {
        $fields = [
            'FirstName' => 'First Name',
            'LastName' => 'Last Name',
            'Year' => 'Year',
            'Num' => 'Submission Count',
        ];
        return $fields;
    }
}

/**
 * A custom report that generates the approvals by security architect per month per year
 */
class SDLTReports_ApprovalsBySAPerMonthPerYear extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return 'Approvals by security architect per month per year';
    }

    /**
     * @param mixed|null $params    
     * @return ArrayList
     */
    public function sourceRecords($params = null)
    {
        $submissions = QuestionnaireSubmission::get()->filter([
            'SecurityArchitectApprovalStatus' => ['approved'],
        ]);

        $grouped = [];
        foreach ($submissions as $submission) {
            $key = $submission->SecurityArchitectApprover->FirstName . '_' . $submission->SecurityArchitectApprover->Surname.
                    substr($submission->Created, 0, 4).substr($submission->Created, 5, 2);

            if (array_key_exists($key, $grouped)) {
                $grouped[$key]['Num']++;
            } else {
                $grouped[$key] = [
                    'ArchitectName' => $submission->SecurityArchitectApprover->FirstName." ".$submission->SecurityArchitectApprover->Surname,
                    'Year' => substr($submission->Created, 0, 4),
                    'Month' => substr($submission->Created, 5, 2),
                    'Num' => 1,
                ];
            }
        }
        $year = array_column($grouped, 'Year');
        $month = array_column($grouped, 'Month');
        $count = array_column($grouped, 'Num');
        array_multisort($year, SORT_DESC, $month, SORT_DESC, $count, SORT_DESC, $grouped);

        return ArrayList::create($grouped);
    }

    /**
     * @return string[] $fields
     */
    public function columns()
    {
        $fields = [
            'ArchitectName' => 'Architect',
            'Year' => 'Year',
            'Month' => 'Month',
            'Num' => 'Submission Count',
        ];
        return $fields;
    }
}
/**
 * A custom report that generates the approvals by security architect per pillar per year
 */
class SDLTReports_ApprovalsBySAPerPillarPerYear extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return 'Approvals by security architect per pillar per year';
    }

     /**
     * @param mixed|null $params    
     * @return ArrayList
     */
    public function sourceRecords($params = null)
    {
        $submissions = QuestionnaireSubmission::get()->filter([
            'SecurityArchitectApprovalStatus' => ['approved'],
        ]);

        $grouped = [];
        foreach ($submissions as $submission) {
            $key = $submission->SecurityArchitectApprover->FirstName . '_' . $submission->SecurityArchitectApprover->Surname.
            substr($submission->Created, 0, 4). $submission->Questionnaire()->Name;

            if (array_key_exists($key, $grouped)) {
                $grouped[$key]['Num']++;
            } else {
                $grouped[$key] = [
                    'ArchitectName' => $submission->SecurityArchitectApprover->FirstName." ".$submission->SecurityArchitectApprover->Surname,
                    'Name' => $submission->Questionnaire()->Name,
                    'Year' => substr($submission->Created, 0, 4),
                    'Num' => 1,
                ];
            }
        }
        $year = array_column($grouped, 'Year');;
        $count = array_column($grouped, 'Num');
        array_multisort($year, SORT_DESC, $count, SORT_DESC, $grouped);

        return ArrayList::create($grouped);
    }

    /**
     * @return string[] $fields
     */
    public function columns()
    {
        $fields = [
            'Name' => 'Pillar',
            'ArchitectName' => 'Architect',
            'Year' => 'Year',
            'Num' => 'Submission Count',
        ];
        return $fields;
    }
}
/**
 * A custom report that generates the number of taks per year (task type)
 */
class SDLTReports_TasksPerYear extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return 'Number of tasks per year (task type)';
    }

    /**
     * @param mixed|null $params    
     * @return ArrayList
     */
    public function sourceRecords($params = null)
    {
        $submissions = TaskSubmission::get()->filterAny([
            'Status' => ['approved', 'complete']
        ]);

        $grouped = [];
        foreach ($submissions as $submission) {

            $key = $submission->Task->Name . substr($submission->Created, 0, 4) ;

            if (array_key_exists($key, $grouped)) {
                $grouped[$key]['Num']++;
            } else {
                $grouped[$key] = [
                    'TaskName' => $submission->Task->Name,
                    'Year' => substr($submission->Created, 0, 4),
                    'Num' => 1,
                ];
            }
        }
         $year = array_column($grouped, 'Year');
         $taskName = array_column($grouped, 'TaskName');
         $count = array_column($grouped, 'Num');\
         array_multisort($year, SORT_DESC, $taskName, SORT_ASC, $count, SORT_DESC, $grouped);

        return ArrayList::create($grouped);
    }

    /**
     * @return string[] $fields
     */
    public function columns()
    {
        $fields = [
            'TaskName' => 'Task Name',
            'Year' => 'Year',
            'Num' => 'Task Count',
        ];
        return $fields;
    }
}
/**
 * A custom report that generates the number of tasks per month per year (task type)
 */
class SDLTReports_TasksPerMonthPerYear extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return 'Number of tasks per month per year (task type)';
    }

    /**
     * @param mixed|null $params    
     * @return ArrayList
     */
    public function sourceRecords($params = null)
    {
        $submissions = TaskSubmission::get()->filterAny([
            'Status' => ['approved', 'complete']
        ]);

        $grouped = [];
        foreach ($submissions as $submission) {
            $key = $submission->Task->Name . substr($submission->Created, 0, 4) . substr($submission->Created, 5, 2);

            if (array_key_exists($key, $grouped)) {
                $grouped[$key]['Num']++;
            } else {
                $grouped[$key] = [
                    'TaskName' => $submission->Task->Name,
                    'Year' => substr($submission->Created, 0, 4),
                    'Month' => substr($submission->Created, 5, 2),
                    'Num' => 1,
                ];
            }
        }
        $year = array_column($grouped, 'Year');
        $month = array_column($grouped, 'Month');
        $taskName = array_column($grouped, 'TaskName');
        $count = array_column($grouped, 'Num');
        array_multisort($year, SORT_DESC, $month, SORT_DESC, $taskName, SORT_ASC, $count, SORT_DESC, $grouped);

        return ArrayList::create($grouped);
    }

    /**
     * @return string[] $fields
     */
    public function columns()
    {
        $fields = [
            'TaskName' => 'Task Name',
            'Year' => 'Year',
            'Month' => 'Month',
            'Num' => 'Task Count',
        ];
        return $fields;
    }
}
/**
 * A custom report that generates the time between creation and approval
 */
class SDLTReports_TimeBetweenCreationAndApproval extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return 'Time between creation and approval';
    }

    /**
     * @param mixed|null $params    
     * @return ArrayList
     */
    public function sourceRecords($params = null)
    {
        $submissions = QuestionnaireSubmission::get()->filter([
            'QuestionnaireStatus' => ['approved']
        ]);

        $grouped = [];
        foreach ($submissions as $submission) {
            $createdDate = new DateTime(substr($submission->Created, 0, 10));
            $lastEdited = new DateTime (substr($submission->LastEdited, 0, 10));
            $dateDifference = $lastEdited->diff($createdDate)->format("%a");
            $key = $dateDifference;

            if (array_key_exists($key, $grouped)) {
                $grouped[$key]['Num']++;
            } else {
                $grouped[$key] = [
                    'DaysElasped' => $dateDifference,
                    'Num' => 1,
                ];
            }
        }
        ksort($grouped);

        return ArrayList::create($grouped);
    }

    /**
     * @return string[] $fields
     */
    public function columns()
    {
        $fields = [
            'DaysElasped' => 'Number of Days',
            'Num' => 'Number of Submissions',
        ];
        return $fields;
    }
}

/**
 * A custom report that generates the time between submitted for approval and fully approved
 */
class SDLTReports_TimeBetweenSubmittedAndApproval extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return 'Time between submitted for approval and fully approved';
    }

    /**
     * @param mixed|null $params    
     * @return ArrayList
     */
    public function sourceRecords($params = null)
    {
        $submissions = QuestionnaireSubmission::get()->filter([
            'QuestionnaireStatus' => ['approved'],
            'SubmittedForApprovalDate:not' => [null],
            'LastEdited:not' => [null],
        ]);

        $grouped = [];
        foreach ($submissions as $submission) {
            $lastEditedDate = new DateTime(substr($submission->LastEdited, 0, 10));
            $submittedApprovalDate = new DateTime(substr($submission->SubmittedForApprovalDate, 0, 10));
            $dateDifference = $lastEditedDate->diff($submittedApprovalDate)->format("%a");
            $key = $dateDifference;

            if (array_key_exists($key, $grouped)) {
                $grouped[$key]['Num']++;
            } else {
                $grouped[$key] = [
                    'DaysElasped' => $dateDifference,
                    'Num' => 1,
                ];
            }
        }
        ksort($grouped);

        return ArrayList::create($grouped);
    }

    /**
     * @return string[] $fields
     */
    public function columns()
    {
        $fields = [
            'DaysElasped' => 'Number of Days',
            'Num' => 'Number of Submissions',
        ];
        return $fields;
    }
}

/**
 * A custom report that generates the time between awaiting approval and security architect approval
 */
class SDLTReports_TimeBetweenApprovalAndSAApproval extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return 'Time between "awaiting approval" and security architect approval';
    }

    /**
     * @param mixed|null $params    
     * @return ArrayList
     */
    public function sourceRecords($params = null)
    {
        $submissions = QuestionnaireSubmission::get()->filter([
            'SecurityArchitectApprovalStatus' => ['approved'],
            'SecurityArchitectStatusUpdateDate:not' => [null],
            'SubmittedForApprovalDate:not' => [null],
        ]);

        $grouped = [];
        foreach ($submissions as $submission) {
            $SAStatusUpdateDate = new DateTime(substr($submission->SecurityArchitectStatusUpdateDate, 0, 10));
            $submittedApprovalDate = new DateTime(substr($submission->SubmittedForApprovalDate, 0, 10));
            $dateDifference = $SAStatusUpdateDate->diff($submittedApprovalDate)->format("%a");
            $key = $dateDifference;

            if (array_key_exists($key, $grouped)) {
                $grouped[$key]['Num']++;
            } else {
                $grouped[$key] = [
                    'DaysElasped' => $dateDifference,
                    'Num' => 1,
                ];
            }
        }
        ksort($grouped);

        return ArrayList::create($grouped);
    }

    /**
     * @return string[] $fields
     */
    public function columns()
    {
        $fields = [
            'DaysElasped' => 'Number of Days',
            'Num' => 'Number of Submissions',
        ];
        return $fields;
    }
}
/**
 * A custom report that generates all penetration tasks that have been completed
 */
class SDLTReports_CompletedPenetrationTests extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return 'Show all penetration tests that have been completed';
    }

    /**
     * @param mixed|null $params
     * @return ArrayList
     */
    public function sourceRecords($params = null)
    {
        $submissions = TaskSubmission::get()->filter([
            'Task.Name' => ['Penetration Test'],
            'Status' => ['approved', 'complete'],
            'QuestionnaireSubmission.ProductName:not' => [null]
        ])->sort('CompletedAt DESC');

        $records = [];
        foreach ($submissions as $submission) {
            $records[] = [
                'ProductName' => $submission->QuestionnaireSubmission->ProductName,
                'Submitter' => $submission->QuestionnaireSubmission->SubmitterName,
                'SubmitterEmail' => $submission->QuestionnaireSubmission->SubmitterEmail,
                'BusinessOwner' => $submission->QuestionnaireSubmission->BusinessOwnerEmailAddress,
                'Date' => $submission->CompletedAt,
                'Link' => $submission->QuestionnaireSubmission->getSummaryPageLink(),
            ];
        }

        return ArrayList::create($records);
    }

    /**
     * @return string[] $fields
     */
    public function columns()
    {
        $fields = [
            'ProductName' => 'Product Name',
            'Submitter' => 'Submitter',
            'SubmitterEmail' => 'Submitter Email',
            'BusinessOwner' => 'Business Owner Contact',
            'Date' => 'Date',
            'Link' => 'Link',
        ];

        return $fields;
    }
}
