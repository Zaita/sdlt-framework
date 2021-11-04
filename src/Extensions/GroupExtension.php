<?php

/**
 * This file contains the "GroupExtension" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2018 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Extension;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Group;
use NZTA\SDLT\Model\Questionnaire;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Class GroupExtension
 */
class GroupExtension extends DataExtension
{
    /**
     * @var array
     */
    private static $belongs_many_many = [
        'Questionnaires' => Questionnaire::class,
    ];

    /**
     * find or create a new group from the given $title string
     *
     * @param string $title Title of the group
     * @return DataObject
     */
    public static function find_or_make_by_name($title)
    {
        $groupInDB = Group::get()->filter([
            'Title' => $title
        ])->first();

        // if group doesn't exist with the given name then create one.
        if (empty($groupInDB)) {
            $newgroup = Group::create();
            $newgroup->Title = $title;
            $newgroup->write();
            $groupInDB = $newgroup;
        }

        return $groupInDB;
    }

    /**
     * return Security Architect Group details
     *
     * @return Object
     */
    public static function security_architect_group()
    {
        $config = SiteConfig::current_site_config();
        return $config->SecurityArchitectGroup();
    }

    /**
     * return CISO Group details
     *
     * @return Object
     */
    public static function ciso_group()
    {
        $config = SiteConfig::current_site_config();
        return $config->CisoGroup();
    }

    /**
     * return Accreditation Authority Group details
     *
     * @return Object
     */
    public static function accreditation_authority_group()
    {
        $config = SiteConfig::current_site_config();
        return $config->AccreditationAuthorityGroup();
    }

    /**
     * return Certification Authority Group details
     *
     * @return Object
     */
    public static function certification_authority_group()
    {
        $config = SiteConfig::current_site_config();
        return $config->CertificationAuthorityGroup();
    }
}
