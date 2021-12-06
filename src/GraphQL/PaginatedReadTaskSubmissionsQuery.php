<?php

/**
 * This file contains the "PaginatedReadTaskSubmissionsQuery" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2021 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\GraphQL;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use NZTA\SDLT\Model\TaskSubmission;
use SilverStripe\Security\Member;
use SilverStripe\GraphQL\Pagination\Connection;
use SilverStripe\GraphQL\Pagination\PaginatedQueryCreator;
use SilverStripe\Security\Security;
use SilverStripe\Security\Group;
use SilverStripe\Core\Convert;

/**
 * Class PaginatedReadTaskSubmissionsQuery
 */
class PaginatedReadTaskSubmissionsQuery extends PaginatedQueryCreator
{
    /**
     * Used to query paginated awaiting task approvals
     * @return connection
     */
    public function createConnection()
    {
        return Connection::create('paginatedReadTaskSubmissions')
            ->setConnectionType($this->manager->getType('NZTATaskSubmission'))
            ->setArgs([
                'UserID' => [
                    'type' => Type::string()
                ],
                'PageType' => [
                    'type' => Type::string()
                ]
            ])
            ->setConnectionResolver(function ($object, array $args, $context, ResolveInfo $info) {
                $member = Security::getCurrentUser();
                $userID = isset($args['UserID']) ? (int) $args['UserID'] : null;
                $pageType = isset($args['PageType']) ? Convert::raw2sql(trim($args['PageType'])) : '';

                if (!$userID) {
                    throw new Exception('Sorry, there is no user ID.');
                }

                if (!empty($userID) && $member->ID != $userID) {
                    throw new Exception('Sorry, wrong user ID.');
                }

                if ($userID && $pageType=="awaiting_approval_list") {
                    $groupIds = $member->groups()->column('ID');

                    $data = TaskSubmission::get()
                        ->filter(['ApprovalGroupID' => $groupIds])
                        ->filter('Status', TaskSubmission::STATUS_WAITING_FOR_APPROVAL)
                        ->exclude('Status', TaskSubmission::STATUS_INVALID);
                }

                return $data;
            });
}   }

