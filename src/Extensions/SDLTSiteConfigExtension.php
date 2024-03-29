<?php

/**
 * Site Config Extension for SDLT Tool
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2018 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Extension;

use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ScaffoldingProvider;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\HTMLEditor\HtmlEditorField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\EmailField;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Security\Group;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\NumericField;
use TractorCow\Colorpicker\Color;
use TractorCow\Colorpicker\Forms\ColorField;

/**
 * Site Config Extension for SDLT Tool
 */
class SDLTSiteConfigExtension extends DataExtension implements ScaffoldingProvider
{
    /**
     * @var array
     */
    private static $db = [
        'AlertEnabled' => 'Boolean',
        'AlertMessage' => 'HTMLText',
        'NoScriptAlertMessage' => 'HTMLText',
        'AlternateHostnameForEmail' => 'Varchar(255)',
        'FromEmailAddress' => 'Varchar(255)',
        'DataExportEmailSubject' => 'Text',
        'DataExportEmailBody' => 'HTMLText',
        'EmailSignature' => 'HTMLText',
        'NumberOfDaysForApprovalReminderEmail' => 'Int',
        // Customisation Config
        'FooterCopyrightText' => 'Text',
        'BusinessOwnerAcknowledgementText' => 'Text',
        'CertificationAuthorityAcknowledgementText' => 'Text',
        'AccreditationAuthorityAcknowledgementText' => 'Text',
        'SecurityTeamEmail' => 'Varchar(255)',
        'OrganisationName' => 'Varchar(255)',
        'ThemeBGColour' => 'Color',
        'ThemeHeaderColour' => 'Color',
        'ThemeSubHeaderColour' => 'Color',
        'ThemeSubHeaderBreadcrumbColour' => 'Color',
        'ThemeLinkColour' => 'Color',
        'ThemeHomePageTextColour' => 'Color'
    ];

    private static $defaults = [
      'ThemeBGColour' => '000000',
      'ThemeHeaderColour' => '800000',
      'ThemeSubHeaderColour' => 'ab0000',
      'ThemeSubHeaderBreadcrumbColour' => 'adadad',
      'ThemeLinkColour' => '000ea8',
      'ThemeHomePageTextColour' => '000000'
    ];

    /**
     * Has One relationships
     *
     * @var array
     */
    private static $has_one = [
        'Logo' => Image::class,
        'AuthLogo' => Image::class,
        'LoginHeroImage' => Image::class,
        // Customisation Config
        'HomePageBackgroundImage' => Image::class,
        'QuestionnairePdfHeaderImage' => Image::class,
        'QuestionnairePdfFooterImage' => Image::class,
        'CertificationAndAccreditationReportLogo' => Image::class,
        'FavIcon' => Image::class,
        'SecurityArchitectGroup' => Group::class,
        'CisoGroup' => Group::class,
        'AssuranceAdminGroup' => Group::class,
        'CertificationAuthorityGroup' => Group::class,
        'AccreditationAuthorityGroup' => Group::class,
        'HomePageSubHeaderImage' => Image::class
    ];

    /**
     * Ownership relationships - automatically publish these records
     *
     * @var array
     */
    private static $owns = [
        'Logo',
        'AuthLogo',
        'LoginHeroImage',
        'HomePageBackgroundImage',
        'QuestionnairePdfHeaderImage',
        'QuestionnairePdfFooterImage',
        'FavIcon',
        'CertificationAndAccreditationReportLogo',
        'HomePageSubHeaderImage'
    ];

    /**
     * CMS fields for siteconfig extension
     *
     * @param FieldList $fields fields passed into the extension
     * @return void
     */
    public function updateCMSFields(FieldList $fields)
    {
        // Main Tab
        $fields->dataFieldByName('Title')
            ->setDescription('This title is displayed in the HTML &lt;title&gt;, and at the top of most screens.');
        $fields->removeByName('Tagline');

        // "Main" tab
        $fields->addFieldsToTab(
            'Root.Main',
            [
                LiteralField::create(
                    'MainIntro',
                    '<p class="message notice">Configure general SDLT settings.</p>'
                )
            ],
            'Title'
        );

        $fields->addFieldsToTab(
            'Root.Main',
            [
                TextField::create(
                    'OrganisationName',
                    'Organisation Name'
                )
            ]
        );

        // Theme Tab
        $fields->addFieldsToTab(
          'Root.Theme',
          [
            ColorField::create('ThemeBGColour', 'Background color'),
            ColorField::create('ThemeHeaderColour', 'Header color'),
            ColorField::create('ThemeSubHeaderColour', 'Sub-Header color'),
            ColorField::create('ThemeSubHeaderBreadcrumbColour', 'Breadcrumb color'),
            ColorField::create('ThemeLinkColour', 'Hyperlink color'),
            ColorField::create('ThemeHomePageTextColour', 'Home Page Text color'),
          ]
        );

        // "Access" tab
        $fields->addFieldToTab(
            'Root.Access',
            LiteralField::create(
                'AlertIntroAccess',
                '<p class="message notice">Configure who can do what within the SDLT.</p>'
            ),
            'CanViewType'
        );

        // "Email" tab
        $fields->addFieldsToTab(
            'Root.Email',
            [
                LiteralField::create(
                    'AlertIntroEmail',
                    '<p class="message notice">Configure some aspects of how email is treated in the system.</p>'
                ),
                TextField::create(
                    'AlternateHostnameForEmail',
                    'Alternate hostname for email'
                )->setDescription(
                    'This setting is used to configure an alternate hostname for use in outgoing email messages. It is'
                    . ' intended to be used in situations where the hostname of the server differs from the URL users'
                    . ' use to log into the website, such as a proxy server or a web application firewall (WAF).'
                ),
                LiteralField::create(
                    'SecurityTeamEmailIntro',
                    '<p class="message notice">Configure the email displayed for the security team.</p>'
                ),
                TextField::create(
                    'SecurityTeamEmail',
                    'Security team email'
                )->setDescription(
                    'This email is displayed as a link to the contact the security team on the Questionnaire Summary page.'
                ),
                ToggleCompositeField::create(
                    'DataExportEmailToggle',
                    'Data Export Email',
                    [
                        EmailField::create(
                            'FromEmailAddress'
                        ),
                        HtmlEditorField::create(
                            'EmailSignature'
                        )
                            ->setRows('3'),
                        TextField::create(
                            'DataExportEmailSubject',
                            'Email Subject'
                        ),
                        HtmlEditorField::create(
                            'DataExportEmailBody',
                            'Email Body'
                        )
                            ->setRows(10)
                            ->setDescription(
                                '<p class="message notice">You can use the following variable substitutions
                                in the email body and subject:<br/><br/>' .
                                '<b>{$dataClass}</b> For exported data class<br/>' .
                                '<b>{$dataName}</b> For exported data name<br/>' .
                                '<b>{$fileName}</b> For file name<br/>' .
                                '<b>{$userName}</b> For user name<br/>' .
                                '<b>{$userEmail}</b> For user email.</p>'
                            )
                    ]
                )
            ]
        );

        // "Images" tab
        $fields->addFieldsToTab(
            'Root.Images',
            [
                LiteralField::create(
                    'ImagesIntro',
                    '<p class="message notice">Configure how various images and logos appear to users.</p>'
                ),
                UploadField::create('AuthLogo', 'Login screen logo')
                    ->setDescription('This is the logo that appears within the authentication screens.'),
                UploadField::create('Logo', 'Header Logo')
                    ->setDescription('This is the logo that appears in the header.
                    The default dimensions for the logo are 370px x 82px.'),
                UploadField::create('LoginHeroImage', 'Login screen background image')
                    ->setDescription('This is the background image shown on the login screen.'),
                UploadField::create('HomePageSubHeaderImage', 'Home Page Sub Header Image')
                    ->setDescription('This is the image that appears underneath the header on the home-screen.'),
                UploadField::create('HomePageBackgroundImage', 'Home Page Background Image')
                    ->setDescription('This is the background image shown on the home-screen.'),
                UploadField::create('FavIcon', 'FavIcon')
                    ->setDescription('This is the site favicon shown on front-end browser tabs.
                    Require: .ico format, dimensions of 16x16, 32x32, or 48x48.')
                    ->setAllowedExtensions(['ico']),
                UploadField::create(
                    'CertificationAndAccreditationReportLogo',
                    'Certification And Accreditation Report Logo'
                )
                    ->setDescription('This is the logo that appears in the
                        certification and accreditaion report.')
            ]
        );

        // "Alert" tab.
        $fields->addFieldsToTab(
            'Root.Alert',
            [
                LiteralField::create(
                    'AlertIntro',
                    '<p class="message notice">Check the box below, to display '
                    .'a global banner-message along the top of each screen.</p>'
                ),
                CheckboxField::create(
                    'AlertEnabled',
                    'Alert Enabled'
                ),
                HtmlEditorField::create(
                    'AlertMessage',
                    'Alert Message'
                )
                    ->setRows(5),
                HtmlEditorField::create(
                    'NoScriptAlertMessage',
                    'Javascript disabled Alert Message'
                )->setRows(5)
            ]
        );

        // "PDF" tab
        $fields->addFieldsToTab(
            'Root.PDF',
            [
                LiteralField::create(
                    'PDFIntro',
                    '<p class="message notice">Configure how generated PDFs appear to users.</p>'
                ),
                UploadField::create('QuestionnairePdfHeaderImage'),
                UploadField::create('QuestionnairePdfFooterImage')
            ]
        );

        // "Footer" tab
        $fields->addFieldsToTab(
            'Root.Footer',
            [
                LiteralField::create(
                    'FooterIntro',
                    '<p class="message notice">Configure how the global footer appears to users.</p>'
                ),
                TextField::create(
                    'FooterCopyrightText',
                    'Footer Text'
                )
            ]
        );

        //workflow tab
        $fields->addFieldsToTab(
            'Root.Workflow Emails',
            [
                DropdownField::create(
                    'SecurityArchitectGroupID',
                    'Security Architect Group',
                    Group::get()->map('ID', 'Title')
                )
                    ->setDescription(
                    'These people will receive emails when
                    a submission is sent for approval once first submitted.'),

                DropdownField::create(
                    'CisoGroupID',
                    'Ciso Group',
                    Group::get()->map('ID', 'Title')
                )
                    ->setDescription(
                    'These users in this group will receive
                    emails when a submission is sent for approval once the above
                    security architect/analysts group has approved.'),

                DropdownField::create(
                    'CertificationAuthorityGroupID',
                    'Certification Authority Group',
                    Group::get()->map('ID', 'Title')
                ),

                DropdownField::create(
                    'AccreditationAuthorityGroupID',
                    'Accreditation Authority Group',
                    Group::get()->map('ID', 'Title')
                ),

                DropdownField::create(
                    'AssuranceAdminGroupID',
                    'Assurance Admin Group',
                    Group::get()->map('ID', 'Title')
                ),

                // reminder email
                LiteralField::create(
                    'ReminderEmails',
                    '<p class="message notice">Reminder Emails </p>'
                ),
                NumericField::create(
                    'NumberOfDaysForApprovalReminderEmail',
                    'Resend approval emails'
                )
                    ->setDescription(
                        'Set the number of days to resend the approval
                        emails to the Business Owner (if applicable) and CISO groups.'
                    ),
            ]
        );


        // "Acknowledgement " tab
        $fields->addFieldsToTab(
            'Root.Acknowledgements',
            [
                TextareaField::create(
                    'BusinessOwnerAcknowledgementText',
                    'Business Owner Acknowledgement Text'
                ),
                TextareaField::create(
                    'CertificationAuthorityAcknowledgementText',
                    'Certification Authority Acknowledgement Text'
                ),
                TextareaField::create(
                    'AccreditationAuthorityAcknowledgementText',
                    'Accreditation Authority Acknowledgement Text'
                ),
                LiteralField::create(
                    'QuestionnaireAcknowledgementText',
                    '<p class="message notice">You can use the following variable substitutions in the acknowledgement text:<br/><br/>' .
                    '<b>{$serviceName}</b> For service name taken from the Certification and Accreditation Memo task<br/>' .
                    '<b>{$expirationDate}</b> Expiration date of the Certification and Accreditation taken from the Certification and Accreditation Memo task<br/>' .
                    '<b>{$accreditationDuration}</b> Taken from Certification and Accreditation task as recommended duration.<br/>' .
                    '<b>{$accreditationType}</b> Service or Change based on the type picked in the Certification and Accreditation Memo Task</p>'
                )
            ]
        );
    }

    /**
     * @param SchemaScaffolder $scaffolder generic comment
     * @return SchemaScaffolder
     */
    public function provideGraphQLScaffolding(SchemaScaffolder $scaffolder)
    {
        $scaffolder
            ->type(SiteConfig::class)
            ->addFields([
                'Title',
                'FooterCopyrightText',
                'LogoPath',
                'HomePageBackgroundImagePath',
                'PdfHeaderImageLink',
                'PdfFooterImageLink',
                'SecurityTeamEmail',
                'HomePageSubHeaderImagePath',
                'ThemeBGColour',
                'ThemeHeaderColour',
                'ThemeSubHeaderColour',
                'ThemeSubHeaderBreadcrumbColour',
                'ThemeLinkColour',
                'ThemeHomePageTextColour'
            ])
            ->operation(SchemaScaffolder::READ)
            ->setName('readSiteConfig')
            ->setUsePagination(false)
            ->setResolver(function ($object, array $args, $context, ResolveInfo $info) {
                $config = SiteConfig::current_site_config();
                return [$config];
            })
            ->end();

        return $scaffolder;
    }

    /**
     * onBeforeWrite
     *
     * @return void
     */
    public function onBeforeWrite()
    {
        if ($this->owner->AlternateHostnameForEmail) {
            //strip whitespace characters from both sides of the URL
            $this->owner->AlternateHostnameForEmail = trim($this->owner->AlternateHostnameForEmail);
            //also strip / just in case it's there.
            $this->owner->AlternateHostnameForEmail = rtrim($this->owner->AlternateHostnameForEmail, '/');
            //we're now guaranteed to have a URL without a trailing slash so if we add one now it's consistently present
            $this->owner->AlternateHostnameForEmail .= '/';
        }
    }

    /**
      * Called from provideGraphQLScaffolding().
      *
      * @return string
      */
    public function getLogoPath() : string
    {
        return (string) $this->owner->Logo()->Link();
    }

    /**
     * Called from provideGraphQLScaffolding().
     *
     * @return string
     */
    public function getHomePageBackgroundImagePath() : string
    {
        return (string) $this->owner->HomePageBackgroundImage()->Link();
    }

    /**
     * Called from provideGraphQLScaffolding().
     *
     * @return string
     */
    public function getPdfHeaderImageLink() : string
    {
        return (string) $this->owner->QuestionnairePdfHeaderImage()->Link();
    }

    /**
     * Called from provideGraphQLScaffolding().
     *
     * @return string
     */
    public function getPdfFooterImageLink() : string
    {
        return (string) $this->owner->QuestionnairePdfFooterImage()->Link();
    }

    /**
     * Called from provideGraphQLScaffolding().
     *
     * @return string
     */
    public function getHomePageSubHeaderImagePath() : string
    {
        return (string) $this->owner->HomePageSubHeaderImage()->Link();
    }

    // public function getThemeBGColour() : string
    // {
    //   if (is_null($this->owner->getField('ThemeLinkColour')))
    //   {
    //     echo "NULL BABY";
    //   }
    //   return $config->owner->ThemeBGColour;
    // }
}
