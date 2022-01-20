<?php

/**
 * This file contains the "PaginatedReadQuestionnaireSubmissionsQuery" class.
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
use NZTA\SDLT\Model\QuestionnaireSubmission;
use SilverStripe\Security\Member;
use SilverStripe\GraphQL\Pagination\Connection;
use SilverStripe\GraphQL\Pagination\PaginatedQueryCreator;
use SilverStripe\Security\Security;
use SilverStripe\Security\Group;
use SilverStripe\Core\Convert;

/**
 * Class PaginatedReadQuestionnaireSubmissionsQuery
 */
class PaginatedReadQuestionnaireSubmissionsQuery extends PaginatedQueryCreator
{
    /**
     * Used to query paginated awaiting questionnaire approvals
     * @return connection
     */
    public function createConnection()
    {
        return Connection::create('paginatedReadQuestionnaireSubmissions')
            ->setConnectionType($this->manager->getType('NZTAQuestionnaireSubmission'))
            ->setArgs([
                'UUID' => [
                    'type' => Type::string()
                ],
                'UserID' => [
                    'type' => Type::string()
                ],
                'SecureToken' => [
                    'type' => Type::string()
                ],
                'PageType' => [
                    'type' => Type::string()
                ]
            ])
            ->setConnectionResolver(function ($object, array $args, $context, ResolveInfo $info) {
                $member = Security::getCurrentUser();
                $userID = isset($args['UserID']) ? (int) $args['UserID'] : null;
                $uuid = isset($args['UUID']) ? htmlentities(trim($args['UUID'])) : null;
                $secureToken = isset($args['SecureToken']) ? Convert::raw2sql(trim($args['SecureToken'])) : null;
                $pageType = isset($args['PageType']) ? Convert::raw2sql(trim($args['PageType'])) : '';

                // To continue the data fetching, user has to be logged-in or has secure token
                if (!$member && !$secureToken) {
                    throw new GraphQLAuthFailure();
                }

                // Check argument
                if (!$uuid && !$userID) {
                    throw new Exception('Sorry, there is no UUID or user Id.');
                }

                if (!empty($userID) && $member->ID != $userID) {
                    throw new Exception('Sorry, wrong user Id.');
                }

                /* @var $data QuestionnaireSubmission */
                $data = [];

                if ($userID && $pageType == 'awaiting_approval_list') {
                    if ($member->getIsSA() && $member->getIsCISO()) {
                        $status = [
                            QuestionnaireSubmission::STATUS_AWAITING_SA_REVIEW,
                            QuestionnaireSubmission::STATUS_WAITING_FOR_SA_APPROVAL,
                            QuestionnaireSubmission::STATUS_WAITING_FOR_APPROVAL,
                            QuestionnaireSubmission::STATUS_APPROVED,
                            QuestionnaireSubmission::STATUS_DENIED
                        ];

                        $data = QuestionnaireSubmission::get()->filter([
                            'QuestionnaireStatus' => $status
                        ])->filterAny([
                            'SecurityArchitectApprovalStatus' => QuestionnaireSubmission::STATUS_PENDING,
                            'CisoApprovalStatus' => QuestionnaireSubmission::STATUS_PENDING
                        ])->exclude('QuestionnaireStatus', QuestionnaireSubmission::STATUS_EXPIRED);
                    } elseif ($member->getIsSA()) {
                        $data = QuestionnaireSubmission::get()->filter([
                            'QuestionnaireStatus' => [
                                QuestionnaireSubmission::STATUS_AWAITING_SA_REVIEW,
                                QuestionnaireSubmission::STATUS_WAITING_FOR_SA_APPROVAL,
                            ],
                            'SecurityArchitectApprovalStatus' => QuestionnaireSubmission::STATUS_PENDING
                        ])->exclude('QuestionnaireStatus', QuestionnaireSubmission::STATUS_EXPIRED);
                    } elseif ($member->getIsCISO()) {
                        $data = QuestionnaireSubmission::get()->filter([
                            'QuestionnaireStatus' => [
                                QuestionnaireSubmission::STATUS_WAITING_FOR_APPROVAL,
                                QuestionnaireSubmission::STATUS_AWAITING_CERTIFICATION_AND_ACCREDITATION,
                                QuestionnaireSubmission::STATUS_AWAITING_CERTIFICATION,
                                QuestionnaireSubmission::STATUS_AWAITING_ACCREDITATION,
                                QuestionnaireSubmission::STATUS_APPROVED,
                                QuestionnaireSubmission::STATUS_DENIED
                            ],
                            'CisoApprovalStatus' => QuestionnaireSubmission::STATUS_PENDING
                        ])->exclude('QuestionnaireStatus', QuestionnaireSubmission::STATUS_EXPIRED);
                    } elseif ($member->getIsCertificationAuthority()) {
                        $data = QuestionnaireSubmission::get()->filter([
                            'QuestionnaireStatus' => [
                                QuestionnaireSubmission::STATUS_AWAITING_CERTIFICATION_AND_ACCREDITATION,
                                QuestionnaireSubmission::STATUS_AWAITING_CERTIFICATION
                            ],
                            'CertificationAuthorityApprovalStatus' => QuestionnaireSubmission::STATUS_PENDING
                        ])->exclude('QuestionnaireStatus', QuestionnaireSubmission::STATUS_EXPIRED);
                    } elseif ($member->getIsAccreditationAuthority()) {
                        $data = QuestionnaireSubmission::get()->filter([
                            'QuestionnaireStatus' => [
                                QuestionnaireSubmission::STATUS_AWAITING_CERTIFICATION_AND_ACCREDITATION,
                                QuestionnaireSubmission::STATUS_AWAITING_ACCREDITATION
                            ],
                            'AccreditationAuthorityApprovalStatus' => QuestionnaireSubmission::STATUS_PENDING
                        ])->exclude('QuestionnaireStatus', QuestionnaireSubmission::STATUS_EXPIRED);
                    } else {
                        // @todo : We might need to change this logic in future for Story:-
                        // Change behaviour of Business Owner approval Token
                        // https://redmine.catalyst.net.nz/issues/66788
                        $data = QuestionnaireSubmission::get()->filter([
                            'QuestionnaireStatus' => QuestionnaireSubmission::STATUS_WAITING_FOR_APPROVAL,
                            'BusinessOwnerApprovalStatus' => QuestionnaireSubmission::STATUS_PENDING,
                            'BusinessOwnerEmailAddress' => $member->Email
                        ])->exclude('QuestionnaireStatus', QuestionnaireSubmission::STATUS_EXPIRED);
                    }
                }

                // data for my submission list
                if ($userID && $pageType == 'my_submission_list') {
                    $data = QuestionnaireSubmission::get()
                        ->filterAny([
                            'UserID' => $userID,
                            'Collaborators.ID' => $userID
                        ])
                        ->exclude('QuestionnaireStatus', QuestionnaireSubmission::STATUS_EXPIRED);
                }

                // data for my product list
                if ($userID && $pageType == 'my_product_list') {
                    $data = QuestionnaireSubmission::get()
                        ->filter(['BusinessOwnerEmailAddress' => $member->Email])
                        ->exclude('QuestionnaireStatus', QuestionnaireSubmission::STATUS_EXPIRED);
                }

                // If the user is not logged-in and the secure token is not valid, throw error
                if (!empty($secureToken) && !hash_equals($data->ApprovalLinkToken, $secureToken)) {
                    throw new Exception('Sorry, wrong security token.');
                }

                return $data;
            });
    }
}
