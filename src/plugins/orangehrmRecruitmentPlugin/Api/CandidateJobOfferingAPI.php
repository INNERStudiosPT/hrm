<?php

/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Recruitment\Api;

use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Entity\CandidateVacancy;
use OrangeHRM\Entity\WorkflowStateMachine;

class CandidateJobOfferingAPI extends AbstractCandidateActionAPI
{
    public const PARAMETER_WORK_SHIFT_ID = 'workShiftId';
    public const PARAMETER_WORK_SHIFT_WORKER_DECIDES = 'workShiftWorkerDecides';

    /**
     * @OA\Put(
     *     path="/api/v2/recruitment/candidates/{candidateId}/job/offer",
     *     tags={"Recruitment/Candidate Workflow"},
     *     summary="Offer Job to Candidate",
     *     operationId="offer-job-to-candidate",
     *     @OA\PathParameter(
     *         name="candidateId",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="note", type="string"),
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/Recruitment-CandidateHistoryDefaultModel"
     *             ),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response="404", ref="#/components/responses/RecordNotFound")
     * )
     * @inheritDoc
     */
    public function getResultingState(): int
    {
        return WorkflowStateMachine::RECRUITMENT_APPLICATION_ACTION_OFFER_JOB;
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForUpdate(): ParamRuleCollection
    {
        $paramRuleCollection = parent::getValidationRuleForUpdate();
        $paramRuleCollection->addParamValidation(
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    self::PARAMETER_WORK_SHIFT_ID,
                    new Rule(Rules::INT_TYPE)
                ),
                true
            )
        );
        $paramRuleCollection->addParamValidation(
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(
                    self::PARAMETER_WORK_SHIFT_WORKER_DECIDES,
                    new Rule(Rules::BOOL_TYPE)
                ),
                true
            )
        );
        return $paramRuleCollection;
    }

    protected function afterCandidateAction(CandidateVacancy $candidateVacancy, ?\OrangeHRM\Entity\Employee $employee): void
    {
        $this->ensureInnerStudiosOfferTable();

        $workerDecides = $this->getRequestParams()->getBooleanOrNull(
            RequestParams::PARAM_TYPE_BODY,
            self::PARAMETER_WORK_SHIFT_WORKER_DECIDES
        ) ?? false;
        $workShiftId = $this->getRequestParams()->getIntOrNull(
            RequestParams::PARAM_TYPE_BODY,
            self::PARAMETER_WORK_SHIFT_ID
        );

        if (!$workerDecides && is_null($workShiftId)) {
            throw $this->getBadRequestException('Work shift is required');
        }

        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO ohrm_innerstudios_recruitment_offer
                (candidate_id, work_shift_id, worker_decides, updated_at)
             VALUES (:candidateId, :workShiftId, :workerDecides, NOW())
             ON DUPLICATE KEY UPDATE
                work_shift_id = VALUES(work_shift_id),
                worker_decides = VALUES(worker_decides),
                updated_at = NOW()',
            [
                'candidateId' => $candidateVacancy->getCandidate()->getId(),
                'workShiftId' => $workerDecides ? null : $workShiftId,
                'workerDecides' => $workerDecides ? 1 : 0,
            ]
        );
    }

    private function ensureInnerStudiosOfferTable(): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS ohrm_innerstudios_recruitment_offer (
                candidate_id INT NOT NULL,
                work_shift_id INT NULL,
                worker_decides TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (candidate_id)
            )'
        );
    }
}
