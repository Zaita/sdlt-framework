<?php
/**
 * This file contains the "MemberGraphQLProvider" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2018 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\GraphQL;

use Exception;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ScaffoldingProvider;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\DataObjectScaffolder;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Core\Convert;
use SilverStripe\Control\Director;

/**
 * Class MemberGraphQLProvider
 */
class MemberGraphQLProvider implements ScaffoldingProvider
{
    /**
     * @param SchemaScaffolder $scaffolder Scaffolder
     * @return SchemaScaffolder
     */
    public function provideGraphQLScaffolding(SchemaScaffolder $scaffolder)
    {
        $dataObjectScaffolder = $this->provideGraphQLScaffoldingForEntityType($scaffolder);
        $this->provideReadMember($dataObjectScaffolder);
    }

    /**
     * @param SchemaScaffolder $scaffolder Scaffolder
     * @return SchemaScaffolder
     */
    public function provideGraphQLScaffoldingForEntityType(SchemaScaffolder $scaffolder)
    {
        // we have add this consition to resolve dev/build error for
        // Siteconfig.SecurityArchitectGroupID field not found
        if (Director::is_cli()) {
            $fieldsNames = [];
        } else {
            $fieldsNames = [
               'ID',
               'Email',
               'FirstName',
               'Surname',
               'IsSA',
               'IsCISO',
               'IsCertificationAuthority',
               'IsAccreditationAuthority'
            ];
        }

        $dataObjectScaffolder = $scaffolder
            ->type(Member::class)
            ->addFields($fieldsNames);

        return $dataObjectScaffolder;
    }

    /**
     * @param DataObjectScaffolder $scaffolder The scaffolder of the data object
     * @return DataObjectScaffolder
     */
    public function provideReadMember(DataObjectScaffolder $scaffolder)
    {
        $scaffolder
            ->operation(SchemaScaffolder::READ)
            ->setName('readMember')
            ->addArg('Type', 'String')
            ->setUsePagination(false)
            ->setResolver(function ($object, array $args, $context, ResolveInfo $info) {
                $member = Security::getCurrentUser();
                $type = isset($args['Type']) ? Convert::raw2sql(trim($args['Type'])) : 'Current';

                // Check authentication
                if (!$member) {
                    throw new Exception('Please log in first...');
                }

                if ($type == 'Current') {
                    return Member::get()->filter('ID', $member->ID);
                }

                if ($type == 'All') {
                    return Member::get();
                }
            })
            ->end();

        return $scaffolder;
    }
}
