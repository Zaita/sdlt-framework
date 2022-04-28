<?php
/**
 * This file contains the "SecurityRiskAssessmentCalculator" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2019 <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Helper;

use NZTA\SDLT\Model\ControlWeightSet;
use NZTA\SDLT\Model\ImpactThreshold;
use NZTA\SDLT\Model\LikelihoodThreshold;
use NZTA\SDLT\Model\Risk;
use NZTA\SDLT\Model\SecurityControl;
use SilverStripe\Core\Injector\Injectable;
use NZTA\SDLT\Model\RiskRating;

/**
 * This helper class will generate all data required to present the
 * Security Risk Assessment Matrix.
 *
 * Each table has:
 * - Risks
 *      - hasMany Aspects
 *          - hasMany SelectedComponents
 *              - hasMany ImplementedControls
 *              - hasMany RecommendedControls
 * - Weights
 *      - each Control has a Weight associated with this risk. It is keyed in
 *        the following way: [RiskID] => [Impact, Likelihood, ImpactPenalty, LikelihoodPenalty]
 * - Scores
 *      - BaseScore
 *      - Sum of Impact
 *      - Sum of Likelihood
 *      - Sum of ImpactPenalty
 *      - Sum of LikelihoodPenalty
 *      - (more TBC)
 *
 */
class SecurityRiskAssessmentCalculator
{
    use Injectable;

    /**
     * QuestionnaireSubmission
     * for example:- `SecurityRiskAssessmentCalculator::create($qs)`
     * @var [QuestionnaireSubmission] passed in with __constructor
     */
    private $questionnaireSubmission;

    /**
     * When the table data is built, this variable indicates at a high level
     * whether aspects are used for the components
     *
     * @var boolean
     */
    private $sraHasAspects = false;

    /**
     * @param Dataobject $questionnaireSubmission A Questionnaire Submission
     */
    public function __construct($questionnaireSubmission)
    {
        $this->questionnaireSubmission = $questionnaireSubmission;
    }

    /**
     * Getter method for QuestionnaireSubmission
     *
     * @return QuestionnaireSubmission
     */
    public function getQuestionnaireSubmission()
    {
        return $this->questionnaireSubmission;
    }

    /**
     * Setter method for QuestionnaireSubmission
     * @param DataObject $questionnaireSubmission questionnaire submission
     * @return SecurityRiskAssessmentCalculator
     */
    public function setQuestionnaireSubmission($questionnaireSubmission)
    {
        $this->questionnaireSubmission = $questionnaireSubmission;
        return $this;
    }

    /**
     * Get product list from the questionnaire submission
     *
     * @return array
     */
    public function getProductAspectList() : array
    {
        if ($productAspects = $this->questionnaireSubmission->getProductAspects()) {
            return json_decode($productAspects);
        }

        return [];
    }

    /**
     * Get all sibling tasks associated with the questionnaire submission
     *
     * @return null|HasManyList
     */
    public function getSiblingTasks()
    {
        $qs = $this->questionnaireSubmission;

        if ($qs && $qs->exists()) {
            return $qs->TaskSubmissions();
        }

        return null;
    }

    /**
     * Get the associated risk questionnaire task from this submission
     *
     * @return TaskSubmission|null
     */
    public function getRiskQuestionnaireSubmission()
    {
        $siblings = $this->getSiblingTasks();

        if ($siblings) {
            return $siblings->find('Task.TaskType', 'risk questionnaire');
        }

        return null;
    }

    /**
    * Get the submitted risk questionnaire task and get the calculated risk Data
    * @param string $component selected prodouct aspect
    *
    * @return array|null
    */
    public function getRiskQuestionnaireResultData($component = '')
    {
        $riskQuestionnaireSubmission = $this->getRiskQuestionnaireSubmission();
        $finalRiskResult = null;

        if ($riskQuestionnaireSubmission) {
            $riskResultData = json_decode($riskQuestionnaireSubmission->RiskResultData, true);

            if (empty($riskResultData) || !count($riskResultData)) {
                return $finalRiskResult;
            }

            if ($riskQuestionnaireSubmission->checkForMultiComponent($component)) {
                foreach ($riskResultData as $riskResult) {
                    if ($riskResult['productAspect'] === $component) {
                        $finalRiskResult = $riskResult['riskResult'];
                    }
                }
            } else {
                $finalRiskResult = $riskResultData;
            }
            // else if (!$component &&
            //     !empty($riskQuestionnaireSubmission->getProductAspects()) &&
            //     $riskQuestionnaireSubmission->CreateOnceInstancePerComponent) {
            //         $finalRiskResult = null;
            // }
        }

        return $finalRiskResult;
    }

    /**
     * Get the control validation audit tasks for this SRA questionnaire
     *
     * @return TaskSubmission | null
     */
    public function getCVATaskSubmission()
    {
        $siblings = $this->getSiblingTasks();

        if ($siblings) {
            return $siblings->find('Task.TaskType', 'control validation audit');
        }

        return null;
    }

    /**
     * Get the control validation audit tasks for this SRA questionnaire
     *
     * @return TaskSubmission | null
     */
    public function getSRATaskSubmission()
    {
        $siblings = $this->getSiblingTasks();

        if ($siblings) {
            return $siblings->find('Task.TaskType', 'security risk assessment');
        }

        return null;
    }

    /**
    * Get the selected components and controls from this SRA questionnaire
    * This is what was actually submitted by the user
    *
    * @param string $component selected component(productAspect)
    *
    * @return void
    */
    public function getCVATaskResult($component)
    {
        $cvaTaskSubmission = $this->getCVATaskSubmission();

        if ($cvaTaskSubmission) {
            $cvaTaskData = $cvaTaskSubmission->filterCVAResultForComponent($cvaTaskSubmission->CVATaskData, $component);
            return json_decode($cvaTaskData, true);
        }

        return null;
    }

    /**
     * Get the IDs of the selected controls from the CVA tasks
     * This is used to construct a lookup table of weights with an associated
     * risk
     *
     * @return array
     */
    public function getSelectedControlIDsFromCVATask($cvaTaskData)
    {
        $selectedComponentsAndControls = $cvaTaskData;
        if (!$selectedComponentsAndControls) {
            return null;
        }

        $ctrlIds = [];
        foreach ($selectedComponentsAndControls as $component) {
            foreach ($component['controls'] as $ctrl) {
                if (isset($ctrl['id'])) {
                    $ctrlIds[] = $ctrl['id'];
                }
            }
        }

        return $ctrlIds;
    }

    /**
     * Get the IDs of the selected component from the CVA tasks
     * This is used to construct a lookup table of weights with an associated
     * risk
     *
     * @return array
     */
    public function getSelectedComponentIDsFromCVATask($cvaTaskData)
    {
        $selectedComponentsAndControls = $cvaTaskData;

        if (!$selectedComponentsAndControls) {
            return [];
        }

        $componentIds = [];

        foreach ($selectedComponentsAndControls as $component) {
            if (isset($component['id']) &&
                !in_array($component['id'], $componentIds)) {
                $componentIds[] = $component['id'];
            }
        }

        return $componentIds;
    }

    /**
     * Calculate the current likelihood score for this aspect (or risk, if no
     * aspects) We start with 100, then deduct the implemented weights. THEN we
     * add the penalties. The result will be the greater of 1 and that number.
     *
     * With the penalties applied it is possible for the likelihood to exceed
     * 100, which is still acceptable
     *
     * @param int     $sumOfLikelihoodWeights   sum of likelihood weight
     * @param int     $sumOfLikelihoodPenalties sum of likelihood penalties
     * @param boolean $doesRiskHasWeights       does has controlWeightSet
     *
     * @return int
     */
    public function calculateCurrentLikelihoodScore($sumOfLikelihoodWeights, $sumOfLikelihoodPenalties, $doesRiskHasWeights)
    {
        if ($doesRiskHasWeights) {
            $likelihoodScore = (100 - $sumOfLikelihoodWeights) + $sumOfLikelihoodPenalties;
            $score = number_format(max(1, $likelihoodScore), 2);

            $formula = sprintf(
                '%s = max (1, (100 - %s) + %s)',
                $score,
                $sumOfLikelihoodWeights,
                $sumOfLikelihoodPenalties
            );

            return ['score' => $score, 'formula' => $formula];
        }

        return ['score' => 0, 'formula' => 0];
    }

    /**
     * Calculate the current impact score for this aspect (or risk, if no
     * aspects) We start with the base impact score, then deduct the implemented
     * weights. THEN we add the penalties. The result will be the greater of 1
     * and that number.
     *
     * @param int     $sumOfImpactWeights   sum of impact weight
     * @param int     $sumOfImpactPenalties sum of impact penalties
     * @param int     $baseImpactScore      base Impact Score
     * @param boolean $doesRiskHasWeights   does has controlWeightSet
     *
     * @return int
     */
    public function calculateCurrentImpactScore($sumOfImpactWeights, $sumOfImpactPenalties, $baseImpactScore, $doesRiskHasWeights)
    {
        if ($doesRiskHasWeights) {
            $impactScore = ($baseImpactScore - $sumOfImpactWeights) + $sumOfImpactPenalties;
            $score =  number_format(max(1, $impactScore), 2);

            $formula = sprintf(
                '%s = max (1, (%s - %s) + %s)',
                $score,
                $baseImpactScore,
                $sumOfImpactWeights,
                $sumOfImpactPenalties
            );

            return ['score' => $score, 'formula' => $formula];
        }

        return ['score' => 0, 'formula' => 0];
    }

    /**
     * @param int $sumOfLikelihoodPenalty sum of likelihood penalties
     * @return int
     */
    public function calculateMaxLikelihoodPenalty($sumOfLikelihoodPenalty)
    {
        return $likelihoodPenalty = (
            100 +
             $sumOfLikelihoodPenalty
        );
    }

    /**
     * @param int $sumOfImpactPenalty sum of impact penalties
     * @param int $baseImpactScore    base impact
     * @return int
     */
    public function calculateMaxImpactPenalty($sumOfImpactPenalty, $baseImpactScore)
    {
        return $impactWeightPenalty = (
            $baseImpactScore +
            $sumOfImpactPenalty
        );
    }

    /**
     * @param int $score current Impact Score
     * @return DataObject ImpactThreshold
     */
    public function lookupImpactThresholdFromScore($score)
    {
        return ImpactThreshold::match($score);
    }

    /**
     * @param int $score  current Likelihood Score
     * @param int $taskID SRA task Id
     * @return DataObject LikelihoodThreshold
     */
    public function lookupLikelihoodThresholdFromScore($score, $taskID)
    {
        return LikelihoodThreshold::match($score, $taskID);
    }

    /**
     * get details for Current Risk Rating
     * example: $currentRiskRating = $this->lookupCurrentRiskRatingThreshold('Rare', 'Insignificant');
     *
     * @param string $currentLikelihood current calcutlated likelihood
     * @param string $currentImpact     current calcutlated Impact
     * @param int    $taskID            SRA task Id
     *
     * @return DataObject|null
     */
    public function lookupCurrentRiskRatingThreshold($currentLikelihood, $currentImpact, $taskID)
    {
        return RiskRating::match(
            $currentLikelihood,
            $currentImpact,
            $taskID
        );
    }

    /**
     * Normalise the CMS Likelihood Weight for this control against all of the
     * components implemented and recommended controls. The sum of these
     * normalised weights should add up to 100.
     *
     * @param int $likelihood         unaltered control weight obtained
     *                                from the CMS for this particular control
     * @param int $sumOfLikelihood    this is the sum of all likelihoods for this
     *                                component's recommended and implemented controls
     * @param string $evalutionRating evalutionRating
     * @return int/float
     */
    public function normaliseControlLikelihoodWeight($likelihood, $sumOfLikelihood, $evalutionRating)
    {
        $evalutionRatingWeight = SecurityControl::EVALUTION_RATING_WEIGHTS[$evalutionRating];

        if ($sumOfLikelihood > 0) {
            $normalisedWeight = $likelihood / $sumOfLikelihood;
            return number_format((100 * $normalisedWeight) * $evalutionRatingWeight, 2);
        }

        return 0;
    }

    /**
     * Normalise the CMS Impact Weight for this control against all of the
     * component's implemented and recommended controls. The sum of these
     * normalised weights should add up to the base impact score.
     *
     * @param int $impact              unaltered control weight obtained
     *                                from the CMS for this particular control
     * @param int $sumOfImpact        this is the sum of all impacts for this component's
     *                                recommended and implemented controls
     * @param int $baseImpact         the base impact score calculated for this
     *                                risk, obtained from the risk questionnaire results
     * @param string $evalutionRating evalutionRating
     * @return int
     */
    public function normaliseControlImpactWeight($impact, $sumOfImpact, $baseImpact, $evalutionRating)
    {
        $evalutionRatingWeight = SecurityControl::EVALUTION_RATING_WEIGHTS[$evalutionRating];

        if ($sumOfImpact > 0) {
            $normalisedWeight = $impact / $sumOfImpact;
            return number_format(($baseImpact * $normalisedWeight) * $evalutionRatingWeight , 2);
        }

        return 0;
    }

    /**
     * Sum of implemented or recommened controls for the individual
     * weight type (likelihood/impact/likelihoodPenalty/impactPenalty)
     * on the component level
     *
     * @param int $controls list of the implementedControls/recommenedControls for a component of the risk
     * @param int $type     likelihood/impact/likelihoodPenalty/impactPenalty
     * @return int
     */
    public function getControlsSum($controls, $type)
    {
        $sum = 0;

        foreach ($controls as $control) {
            $sum += $control[$type];
        }

        return $sum;
    }

    /**
     * Sum of the components for the individual weight type
     * (likelihood/impact/likelihoodPenalty/impactPenalty) on the risk
     *
     * @param int $components list of components for the risk
     * @param int $type       likelihood/impact/likelihoodPenalty/impactPenalty
     * @return int
     */
    public function getComponetsSum($components, $type)
    {
        $sum = 0;

        foreach ($components as $component) {
            $sum += $component[$type];
        }

        return $sum;
    }

    /**
     * sum of the components weights for the risk
     * 1. sumOfLikelihood = sumOfImplementedAndRecommendedControlsLikelihood for all the components of the risk
     * 2. sumOfImpact = sumOfImplementedAndRecommendedControlsImpact for all the components of the risk
     * 3. sumOfImplementedAndRecommendedControlsLikelihood = sumOfRecommendedControlsLikelihoodPenalty
     * for all the components of the risk
     * 4. sumOfImplementedAndRecommendedControlsLikelihood = sumOfRecommendedControlsImpactPenalty
     * for all the components of the risk
     *
     * @param array $components list of components related to a risk
     * @return array
     */
    public function sumForRiskComponents($components)
    {
        $sum['sumOfLikelihood'] = $this->getComponetsSum(
            $components,
            'sumOfImplementedAndRecommendedControlsLikelihood'
        );
        $sum['sumOfImpact'] = $this->getComponetsSum(
            $components,
            'sumOfImplementedAndRecommendedControlsImpact'
        );
        $sum['sumOfRecommendedLikelihoodPenalty'] = $this->getComponetsSum(
            $components,
            'sumOfRecommendedControlsLikelihoodPenalty'
        );
        $sum['sumOfRecommendedImpactPenalty'] = $this->getComponetsSum(
            $components,
            'sumOfRecommendedControlsImpactPenalty'
        );
        $sum['sumOfLikelihoodPenalty'] = $this->getComponetsSum(
            $components,
            'sumOfControlsLikelihoodPenalty'
        );
        $sum['sumOfImpactPenalty'] = $this->getComponetsSum(
            $components,
            'sumOfControlsImpactPenalty'
        );

        return $sum;
    }

    /**
     * calculate the SRATaskdetails from the RiskQuestionnaireResultData and CVATaskResult
     * @param string $component selected prodouct aspect
     *
     * @return array
     */
    public function getSRATaskdetails($component = '') : array
    {
        $sraTaskDetail = [];
        $riskData = $this->getRiskQuestionnaireResultData($component);

        if (!$riskData) {
            $sraTaskSubmission = $this->getSRATaskSubmission();
            $sraTask = $sraTaskSubmission->Task();
            $sraTaskDetail['likelihoodThresholds'] = $this->getLikelihoodRatingsThresholds($sraTask);
            $sraTaskDetail['riskRatingThresholds'] = $this->getRiskRatingThresholdsMatix($sraTask);
            return $sraTaskDetail;
        }

        $cvaTaskData = $this->getCVATaskResult($component);

        $sraTaskDetail = $this->getRisksAndComponentsAndControlsforSra($cvaTaskData, $riskData);

        return $sraTaskDetail;
    }

    /**
     * @return array LikelihoodRatings
     */
    public function getLikelihoodRatingsThresholds($sraTask = '')
    {
        if (empty ($sraTask)) {
            $sraTask = $this->getSRATaskSubmission()->task();
        }

        return $sraTask->getLikelihoodRatingsData();
    }

    /**
     * @return array RiskRatings matrix
     */
    public function getRiskRatingThresholdsMatix($sraTask = '')
    {
        if (empty ($sraTask)) {
            $sraTask = $this->getSRATaskSubmission()->task();
        }

        return $sraTask->getRiskRatingMatix();
    }

    /**
     * calculate the SRATaskdetails from the RiskQuestionnaireResultData and CVATaskResult
     *
     * @param array $cvaTaskData result data of CVA task Submission
     * @param array $riskdata    result data of risk questionnaire task Submission
     * @return array
     */
    public function getRisksAndComponentsAndControlsforSra($cvaTaskData, $riskdata)
    {
        $sraTaskDetail = [];
        $out = [];

        $controlIds = $cvaTaskData ? $this->getSelectedControlIDsFromCVATask($cvaTaskData) : [];
        $componentIds = $cvaTaskData ? $this->getSelectedComponentIDsFromCVATask($cvaTaskData) : [];
        $sraTaskSubmission = $this->getSRATaskSubmission();
        $sraTask = $sraTaskSubmission->Task();
        $sraTaskID = $sraTask->ID;

        //$sraTaskDetail['likelihoodThresholds'] = $this->getLikelihoodRatingsThresholds($sraTask);
        //$sraTaskDetail['riskRatingThresholds'] = $this->getRiskRatingThresholdsMatix($sraTask);
        // $sraTaskDetail['hasProductAspects'] = false;

        $riskIDs = array_column($riskdata, 'riskID');
        $riskInDB = Risk::get()->byIds($riskIDs)->toNestedArray();

        foreach ($riskdata as $risk) {
            $index = array_search($risk['riskID'], array_column($riskInDB, 'ID'));

            if ($index === false) {
                continue;
            }

            $out['riskId'] = $risk['riskID'];
            $out['riskName'] = $risk['riskName'];
            //$out['description'] = isset($riskInDB[$index]['Description']) ? $riskInDB[$index]['Description'] : '';
            $out['baseImpactScore'] = (int) round($risk['score']);
            $doesRiskHasWeights = false;

            $components = [];

            // foreach risk, query its set of control weights. This is a big performance hit
            if (!empty($controlIds) && $cvaTaskData) {
                $weights = ControlWeightSet::get()->filter(
                    [
                        'RiskID' => $risk['riskID'],
                        'SecurityControlID' => $controlIds,
                        'SecurityComponentID' => $componentIds
                    ]
                );

                if ($weights->count()) {
                    $doesRiskHasWeights = true;

                    $controlRiskWeights = [];

                    foreach ($weights as $weight) {
                        // index with selected component and control (create multi multidimensional array)
                        // add Impact, Likelihood, ImpactPenalty, LikelihoodPenalty
                        $controlRiskWeights[$weight->SecurityComponentID][$weight->SecurityControlID] = [
                            'I' => $weight->Impact,
                            'L' => $weight->Likelihood,
                            'IP' => $weight->ImpactPenalty,
                            'LP' => $weight->LikelihoodPenalty
                        ];
                    }

                    foreach ($cvaTaskData as $component) {
                        $components[] = $this->updateComponentDetails(
                            $component,
                            $controlRiskWeights
                        );
                    }
                }
            }

            $riskComponentdetails = $this->getRiskComponentDetails(
                $components,
                $out['baseImpactScore'],
                $sraTaskID,
                $doesRiskHasWeights
            );

            $out['riskDetail']['MaxLikelihoodPenalty'] = $riskComponentdetails['MaxLikelihoodPenalty'];
            $out['riskDetail']['MaxImpactPenalty'] = $riskComponentdetails['MaxImpactPenalty'];
            $out['riskDetail']['currentLikelihood'] = $riskComponentdetails['currentLikelihood'];
            $out['riskDetail']['currentImpact'] = $riskComponentdetails['currentImpact'];
            $out['riskDetail']['currentRiskRating'] = $riskComponentdetails['currentRiskRating'];

            $sraTaskDetail[] = $out;
        }

        return $sraTaskDetail;
    }

    /**
     * update clone component details for the risk
     *
     * @param array $cvaComponent       decoded cva task result data
     * @param array $controlRiskWeights list of control weight set to update controls the component
     *
     * @return array
     */
    public function updateComponentDetails($cvaComponent, $controlRiskWeights) : array
    {
        // we will traverse the same component of the cva task fot the other risk
        // that's we need to deep clone the $cvaComponent
        $cloneComponent = unserialize(serialize($cvaComponent)); // deep clone the component
        $componentId = (string) $cloneComponent['id'];
        $filteredImplementedControls = [];
        $filteredRecommendedControls = [];
        $implementedControls = [];
        $recommendedControls = [];

        if (isset($controlRiskWeights[$componentId])) {
            $updatedControls = $this->updateControlsDetailAndAddWeightSet(
                $cloneComponent['controls'],
                $controlRiskWeights[$componentId]
            );

            $cloneComponent['sumOfControlsLikelihoodPenalty'] =
                $this->getControlsSum($updatedControls, 'likelihoodPenalty');

            $cloneComponent['sumOfControlsImpactPenalty'] =
                $this->getControlsSum($updatedControls, 'impactPenalty');
        } else {
            $cloneComponent['sumOfControlsLikelihoodPenalty'] = 0;
            $cloneComponent['sumOfControlsImpactPenalty'] = 0;
        }

        // get all Implemented Controls for the component
        $filteredImplementedControls = array_filter($cloneComponent['controls'], function ($ctrl) {
            return (
                $ctrl['selectedOption'] === SecurityControl::CTL_STATUS_1 ||
                $ctrl['selectedOption'] === SecurityControl::CTL_STATUS_4
            );
        });

        if ($filteredImplementedControls && isset($controlRiskWeights[$componentId])) {
            $implementedControls = $this->updateControlsDetailAndAddWeightSet(
                $filteredImplementedControls,
                $controlRiskWeights[$componentId]
            );
        }

        // get all recommended Controls for the component
        $filteredRecommendedControls = array_filter($cloneComponent['controls'], function ($ctrl) {
            return $ctrl['selectedOption'] === SecurityControl::CTL_STATUS_2;
        });

        if ($filteredRecommendedControls && isset($controlRiskWeights[$componentId])) {
            $recommendedControls = $this->updateControlsDetailAndAddWeightSet(
                $filteredRecommendedControls,
                $controlRiskWeights[$componentId]
            );
        }

        // update clone component details
        $cloneComponent['implementedControls'] = $implementedControls;
        $cloneComponent['recommendedControls'] = $recommendedControls;

        $cloneComponent['sumOfImplementedAndRecommendedControlsLikelihood'] =
            (int) $this->getControlsSum($implementedControls, 'likelihood') +
            $this->getControlsSum($recommendedControls, 'likelihood');

        $cloneComponent['sumOfImplementedAndRecommendedControlsImpact'] =
            (int) $this->getControlsSum($implementedControls, 'impact') +
            $this->getControlsSum($recommendedControls, 'impact');

        $cloneComponent['sumOfRecommendedControlsLikelihoodPenalty'] =
            $this->getControlsSum($recommendedControls, 'likelihoodPenalty');

        $cloneComponent['sumOfRecommendedControlsImpactPenalty'] =
            $this->getControlsSum($recommendedControls, 'impactPenalty');

        // unset the additional information about the cva comopnent from the clone component
        unset($cloneComponent['controls']);
        unset($cloneComponent['jiraTicketLink']);

        return $cloneComponent;
    }

    /**
     * update control details and add weight set with control
     * (control from cva task) itself to simplify the calculation
     *
     * @param array $filteredControls   implemeted or recommened controls list
     * @param array $controlRiskWeights list of control weight
     *
     * @return array
     */
    public function updateControlsDetailAndAddWeightSet($filteredControls, $controlRiskWeights) : array
    {
        $updatedControls = [];

        // we will loop same component and control for the other risk as well
        // that's why we need to deep clone the controls for the current component of the riskId
        // we don't want to update the actual controls array, will update the cloned one
        $cloneControls = unserialize(serialize($filteredControls)); // deep clone the control

        foreach ($cloneControls as $control) {
            $controlID = $control['id'];

            if (isset($controlRiskWeights[$controlID])) {
                // set control weights set
                $control['impact'] = $controlRiskWeights[$controlID]['I'];
                $control['likelihood'] = $controlRiskWeights[$controlID]['L'];
                $control['impactPenalty'] = $controlRiskWeights[$controlID]['IP'];
                $control['likelihoodPenalty'] = $controlRiskWeights[$controlID]['LP'];

                unset($control['selectedOption']);
                unset($control['description']);

                $updatedControls[] = $control;
            }
        }

        return $updatedControls;
    }

    /**
     * normalise controls weight of the components for the risk and
     * calculate currentLikelihood, currentImpact and currentRiskRating
     * details for the risk
     *
     * @param array $components      list of the compnent
     * @param int   $baseImapctScore base impact score
     * @param int   $sraTaskID       sra task id
     *
     * @return array
     */
    public function getRiskComponentDetails($components, $baseImapctScore, $sraTaskID, $doesRiskHasWeights) : array
    {
        $riskComponentdetails = [
            'MaxLikelihoodPenalty' => 0,
            'MaxImpactPenalty' => 0
        ];

        if (!empty($components) && $doesRiskHasWeights) {
            $riskComponentdetails['components'] = $components;
            $riskComponentdetails['sums']= $this->sumForRiskComponents($components);

            $riskComponentdetails = $this->normaliseControlsWeight(
                $riskComponentdetails,
                $baseImapctScore
            );

            $riskComponentdetails['MaxLikelihoodPenalty'] = $this->calculateMaxLikelihoodPenalty (
                $riskComponentdetails['sums']['sumOfLikelihoodPenalty']
            );

            $riskComponentdetails['MaxImpactPenalty'] = $this->calculateMaxImpactPenalty (
                $riskComponentdetails['sums']['sumOfImpactPenalty'],
                $baseImapctScore
            );
        }

        // calculate current Likelihood
        $riskComponentdetails['currentLikelihood'] = $this->calculateCurrentLikelihoodScore(
            $doesRiskHasWeights ? $riskComponentdetails['sums']['sumOfImplementedLikelihoodWeight'] : 0,
            $doesRiskHasWeights ? $riskComponentdetails['sums']['sumOfRecommendedLikelihoodPenalty'] : 0,
            $doesRiskHasWeights
        );

        // calculate current Impact
        $riskComponentdetails['currentImpact'] = $this->calculateCurrentImpactScore(
            $doesRiskHasWeights ? $riskComponentdetails['sums']['sumOfImplementedImpactWeight'] : 0,
            $doesRiskHasWeights ? $riskComponentdetails['sums']['sumOfRecommendedImpactPenalty'] : 0,
            $baseImapctScore,
            $doesRiskHasWeights
        );

        $currentLikelihoodThreshold = $this->lookupLikelihoodThresholdFromScore(
            $riskComponentdetails['currentLikelihood']['score'],
            $sraTaskID
        );

        $riskComponentdetails['currentLikelihood']['name'] = $currentLikelihoodThreshold ?
            $currentLikelihoodThreshold->Name : '';
        $riskComponentdetails['currentLikelihood']['colour'] = $currentLikelihoodThreshold ?
            $currentLikelihoodThreshold->getHexColour() : null;


        $currentImpactThreshold = $this->lookupImpactThresholdFromScore(
            $riskComponentdetails['currentImpact']['score']
        );
        $riskComponentdetails['currentImpact']['name'] =
            $currentImpactThreshold ? $currentImpactThreshold->Name : '';
        $riskComponentdetails['currentImpact']['colour'] =
            $currentImpactThreshold ? $currentImpactThreshold->getHexColour() : null;

        // calculate current RiskRating based on current Impact and current Likelihood
        $riskRatingThreshold = $this->lookupCurrentRiskRatingThreshold(
            $riskComponentdetails['currentLikelihood']['name'],
            $riskComponentdetails['currentImpact']['name'],
            $sraTaskID
        );
        $riskComponentdetails['currentRiskRating']['name'] =
            $riskRatingThreshold ? $riskRatingThreshold->RiskRating : '';
        $riskComponentdetails['currentRiskRating']['colour'] =
            $riskRatingThreshold ? '#' . $riskRatingThreshold->Colour : null;

        return $riskComponentdetails;
    }

    /**
     * traverse and update all the components and controls of the risk and
     * normalise the CMS likelihood and impact value of the control
     * and sum up all the normalise likelihood and impact value of the implementedControls
     * of the component for the risk
     *
     * @param array $riskComponentdetails list of all component of the risk
     * @param int   $baseImpact           calculated Base Impact Score of the risk
     *
     * @return array
     */
    public function normaliseControlsWeight($riskComponentdetails, $baseImpact)
    {
        $sumOfLikelihoodWeight = 0;
        $sumOfImpactWeight = 0;
        $components = $riskComponentdetails['components'];
        $sums = $riskComponentdetails['sums'];

        foreach ($components as &$component) {
            if (!empty($component['implementedControls'])) {
                foreach ($component['implementedControls'] as &$control) {
                    $control['likelihoodWeight'] = $this->normaliseControlLikelihoodWeight(
                        $control['likelihood'],
                        $sums['sumOfLikelihood'],
                        $control['evalutionRating']
                    );

                    $sumOfLikelihoodWeight += $control['likelihoodWeight'];

                    $control['impactWeight'] = $this->normaliseControlImpactWeight(
                        $control['impact'],
                        $sums['sumOfImpact'],
                        $baseImpact,
                        $control['evalutionRating']
                    );

                    $sumOfImpactWeight += $control['impactWeight'];
                }
            }

            if (!empty($component['recommendedControls'])) {
                foreach ($component['recommendedControls'] as &$control) {
                    $control['likelihoodWeight'] = $this->normaliseControlLikelihoodWeight(
                        $control['likelihood'],
                        $sums['sumOfLikelihood'],
                        $control['evalutionRating']
                    );

                    $control['impactWeight'] = $this->normaliseControlImpactWeight(
                        $control['impact'],
                        $sums['sumOfImpact'],
                        $baseImpact,
                        $control['evalutionRating']
                    );
                }
            }
        }

        $riskComponentdetails['sums']['sumOfImplementedLikelihoodWeight'] = $sumOfLikelihoodWeight;
        $riskComponentdetails['sums']['sumOfImplementedImpactWeight'] = $sumOfImpactWeight;
        $riskComponentdetails['components'] = $components;

        return $riskComponentdetails;
    }
}
