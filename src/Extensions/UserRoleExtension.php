<?php

/**
 * This file contains the "UserRoleExtension" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2018 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Extension;

use NZTA\SDLT\Constant\UserGroupConstant;
use SilverStripe\ORM\DataExtension;
use NZTA\SDLT\Extension\GroupExtension;

/**
 * Class UserRoleExtension
 */
class UserRoleExtension extends DataExtension
{
    /**
     * Check if the member is a Security Architect
     *
     * @return boolean
     */
    public function getIsSA()
    {
        // SA and CISO can view it
        return $this->owner
            ->Groups()
            ->filter('Code', GroupExtension::security_architect_group()->Code)
            ->exists();
    }

    /**
     * Check if the member is a Chief Information Security Officer
     *
     * @return boolean
     */
    public function getIsCISO()
    {
        return $this->owner
            ->Groups()
            ->filter('Code', GroupExtension::ciso_group()->Code)
            ->exists();
    }

    /**
     * Check if the member is a Reporter.
     *
     * @return boolean
     */
    public function getIsReporter()
    {
        return $this->owner
            ->Groups()
            ->filter('Code', UserGroupConstant::GROUP_CODE_REPORTER)
            ->exists();
    }

    /**
     * Check if the member is a SilverStripe administrator
     *
     * @return boolean
     */
    public function getIsAdmin()
    {
        return $this->owner
            ->Groups()
            ->filter('Code', UserGroupConstant::GROUP_CODE_ADMIN)
            ->exists();
    }

    /**
     * Check if the member is Accreditation Authority
     *
     * @return boolean
     */
    public function getIsAccreditationAuthority()
    {
        return $this->owner
            ->Groups()
            ->filter('Code', GroupExtension::accreditation_authority_group()->Code)
            ->exists();
    }

    /**
     * Check if the member is Certification Authority
     *
     * @return boolean
     */
    public function getIsCertificationAuthority()
    {
        return $this->owner
            ->Groups()
            ->filter('Code', GroupExtension::certification_authority_group()->Code)
            ->exists();
    }

    /**
     * Return the role-name for a given user. The returned string is in compound
     * form, but you can use {@link FormField::name_to_label()} to prettify it.
     *
     * @return string
     */
    public function getRoleName() : string
    {
        switch ($this->getOwner()) {
            case $this->getIsSA():
                return UserGroupConstant::ROLE_CODE_SA;
            case $this->getIsCISO():
                return UserGroupConstant::ROLE_CODE_CISO;
            case $this->getIsReporter():
                return UserGroupConstant::ROLE_CODE_REPORTER;
            case $this->getIsAdmin():
                return UserGroupConstant::ROLE_CODE_ADMIN;
            case $this->getIsAccreditationAuthority():
                return UserGroupConstant::ROLE_CODE_ACCREDITATIONAUTHORITY;
            default:
                return '';
        }
    }
}
