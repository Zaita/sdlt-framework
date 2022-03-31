<?php

/**
 * This file contains the "SecurityControl" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2018 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\TextField;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ScaffoldingProvider;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use SilverStripe\Forms\HTMLEditor\HtmlEditorField;
use SilverStripe\ORM\HasManyList;

/**
 * Class SecurityControl
 *
 */
class SecurityControl extends DataObject implements ScaffoldingProvider
{
    /**
     * @var string
     */
    const CTL_STATUS_1 = 'Realised';
    const CTL_STATUS_2 = 'Intended';
    const CTL_STATUS_3 = 'Not Applicable';
    const CTL_STATUS_4 = 'Planned';
    const EVALUTION_RATING_1 = 'Not Validated';
    const EVALUTION_RATING_2 = 'Not Effective';
    const EVALUTION_RATING_3 = 'Partially Effective';
    const EVALUTION_RATING_4 = 'Effective';
    const EVALUTION_RATING_WEIGHTS = [
        "Not Validated" => 1,
        "Not Effective" => 0,
        "Partially Effective" => 0.5,
        "Effective" => 1
    ];

    /**
     * @var string
     */
    private static $table_name = 'SecurityControl';

    /**
     * @var array
     */
    private static $db = [
        'Name' => 'Varchar(255)',
        'Description' => 'HTMLText',
        'ImplementationGuidance' => 'HTMLText',
        'ImplementationEvidence' => 'HTMLText'
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'ControlWeightSets' => ControlWeightSet::class
    ];

    /**
     * @var array
     */
    private static $belongs_many_many = [
        'SecurityComponent' => SecurityComponent::class
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Name' => 'Name',
        'Description' => 'Description',
        'usedOnComponent' => 'Used On',
    ];

    /**
     * @var array
     */
    private static $searchable_fields = [
        'Name',
        'Description'
    ];

    /**
     * Default sort ordering
     * @var array
     */
    private static $default_sort = ['Name' => 'ASC'];

    /**
     * @param SchemaScaffolder $scaffolder Scaffolder
     * @return SchemaScaffolder
     */
    public function provideGraphQLScaffolding(SchemaScaffolder $scaffolder)
    {
        // Provide entity type
        $typeScaffolder = $scaffolder
            ->type(self::class)
            ->addFields([
                'ID',
                'Name',
                'Description',
                'ImplementationGuidance',
                'ImplementationEvidence'
            ]);

        return $typeScaffolder;
    }

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $name = TextField::create('Name')
            ->setDescription('This is the title of the control. It is displayed'
            .' as the title as the line-item of a checklist.');

        $desc = HtmlEditorField::create('Description')
            ->setDescription('This contains the description that appears under'
            .' the title of a line-item in the component checklist.')
            ->setRows(3);

        $implementationGuidance = HtmlEditorField::create('ImplementationGuidance')
            ->setRows(3);

        $implementationEvidence = HtmlEditorField::create('ImplementationEvidence')
            ->setRows(3);

        $fields->addFieldsToTab('Root.Main', [$name, $desc, $implementationGuidance, $implementationEvidence]);
        $fields->removeByName(['SecurityComponent', 'ControlWeightSets']);

        if ($this->ID) {
            // Allow inline-editing
            $componentEditableFields = (new GridFieldEditableColumns())
                ->setDisplayFields([
                    'Likelihood' => [
                        'title' => 'Likelihood',
                        'field' => NumericField::create('Likelihood')
                    ],
                    'Impact' => [
                        'title' => 'Impact',
                        'field' => NumericField::create('Impact')
                    ],
                    'LikelihoodPenalty' => [
                        'title' => 'Likelihood Penalty',
                        'field' => NumericField::create('LikelihoodPenalty')
                    ],
                    'ImpactPenalty' => [
                        'title' => 'Impact Penalty',
                        'field' => NumericField::create('ImpactPenalty')
                    ],
                ]);

            $config = GridFieldConfig_RelationEditor::create()
                ->addComponent($componentEditableFields, GridFieldEditButton::class)
                ->removeComponentsByType(GridFieldAddExistingAutocompleter::class);

            $gridField = GridField::create(
                'ControlWeightSets',
                'Control Weight Sets',
                $this->ControlWeightSets()
                    ->filter(['SecurityComponentID' => $this->getParentComponentID()]),
                $config
            );

            $fields->addFieldsToTab('Root.Main', FieldList::create([
                    LiteralField::create(
                        'ControlWeightSetIntro',
                        '<p class="message notice">A <b>Control Weight Set</b> ' .
                        'is a combination of Risk, Likelihood, Impact and Penalties ' .
                        'that is unique to a Control.</p>'
                    ),
                    $gridField
                ]));
        }

        return $fields;
    }

    /**
     * Get parent component id
     *
     * @return int
     */
    public function getParentComponentID()
    {
        if (Controller::has_curr()) {
            $req = Controller::curr()->getRequest();
            $reqParts = explode('NZTA-SDLT-Model-SecurityComponent/item/', $req->getUrl());

            if (!empty($reqParts) && isset($reqParts[1])) {
                return (int) strtok($reqParts[1], '/');
            }
        }

        return 0;
    }

    /**
     * create control from json import
     *
     * @param object $controls  control json object
     * @param object $component component dataobject
     *
     * @return void
     */
    public static function create_record_from_json($controls, $component)
    {
        foreach ($controls as $control) {
            $controlObj = self::get_by_name($control->name);

            // if obj doesn't exist with the same name then create a new object
            if (empty($controlObj)) {
                $controlObj = self::create();
            }

            $controlObj->Name = $control->name ?? '';

            // control can be reuse with the another component but if description exist then
            // overwrite the control description with new one
            if (property_exists($control, "description")) {
                $controlObj->Description = $control->description;
            }

            if (property_exists($control, "implementationGuidance")) {
                $controlObj->ImplementationGuidance = $control->implementationGuidance;
            }

            if (property_exists($control, "implementationEvidence")) {
                $controlObj->ImplementationEvidence = $control->implementationEvidence;
            }

            // add component to the security component
            $controlObj->SecurityComponent()->add($component);

            // remove the controls weight set for component and control
            $controlWeightSets = $controlObj->ControlWeightSets()->filter(
                ['SecurityComponentID' => $component->ID]
            );

            foreach ($controlWeightSets as $controlWeightSet) {
                $controlWeightSet->delete();
            }

            $controlObj->write();

            // add new control weight set if exist
            if (property_exists($control, "controlWeightSets") &&
                !empty($weights = $control->controlWeightSets)) {
                ControlWeightSet::create_record_from_json($weights, $controlObj, $component);
            }
        }
    }

    /**
     * get security control by name
     *
     * @param string $controlName security control name
     * @return object|null
     */
    public static function get_by_name($controlName)
    {
        $control = SecurityControl::get()
            ->filter(['Name' => $controlName])
            ->first();

        return $control;
    }

    /**
     * @return ValidationResult
     */
    public function validate()
    {
        $result = parent::validate();

        $control = self::get()
            ->filter([
                'Name' => $this->Name
            ])
            ->exclude('ID', $this->ID);

        if ($control->count()) {
            $result->addError(
                sprintf(
                    'Control with name "%s" already exists, please create a unique control.',
                    $this->Name
                )
            );
        }

        return $result;
    }

    /**
     * @return string
     */
    public function usedOnComponent()
    {
        $components = $this->SecurityComponent();
        $componentName = '';

        if ($components) {
            $componentName = implode(", ", $components->column('Name'));
        }

        return $componentName;
    }

    /**
     * export control
     *
     * @param integer $control control
     * @return string
     */
    public static function export_record($control, $componentID)
    {
        $obj['name'] = $control->Name;
        $obj['description'] = $control->Description ?? '';
        $obj['implementationGuidance'] = $control->ImplementationGuidance ?? '';
        $obj['implementationEvidence'] = $control->ImplementationEvidence ?? '';

        foreach ($control->ControlWeightSets() as $weight) {
            if ($weight->SecurityComponentID == $componentID) {
                $obj['controlWeightSets'][] = ControlWeightSet::export_record($weight);
            }
        }

        return $obj;
    }
}
