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
use OrangeHRM\Entity\CandidateVacancy;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\WorkflowStateMachine;

class CandidateHiringAPI extends AbstractCandidateActionAPI
{
    public const PARAMETER_SIGNED_DOCUMENT = 'signedDocument';

    /**
     * * @OA\Put(
     *     path="/api/v2/recruitment/candidates/{candidateId}/hire",
     *     tags={"Recruitment/Candidate Workflow"},
     *     summary="Hire Candidate",
     *     operationId="hire-candidate",
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
     *
     * @inheritDoc
     */
    public function getResultingState(): int
    {
        return WorkflowStateMachine::RECRUITMENT_APPLICATION_ACTION_HIRE;
    }

    protected function afterCandidateAction(CandidateVacancy $candidateVacancy, ?Employee $employee): void
    {
        $this->ensureInnerStudiosTables();
        $this->saveSignedDocument($candidateVacancy);

        if (is_null($employee)) {
            return;
        }

        $offer = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT work_shift_id, worker_decides
             FROM ohrm_innerstudios_recruitment_offer
             WHERE candidate_id = :candidateId',
            ['candidateId' => $candidateVacancy->getCandidate()->getId()]
        );

        if (!$offer || (int)$offer['worker_decides'] === 1 || empty($offer['work_shift_id'])) {
            return;
        }

        $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM ohrm_employee_work_shift WHERE emp_number = :empNumber',
            ['empNumber' => $employee->getEmpNumber()]
        );
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO ohrm_employee_work_shift (emp_number, work_shift_id)
             VALUES (:empNumber, :workShiftId)',
            [
                'empNumber' => $employee->getEmpNumber(),
                'workShiftId' => (int)$offer['work_shift_id'],
            ]
        );
    }

    private function saveSignedDocument(CandidateVacancy $candidateVacancy): void
    {
        $attachment = $this->getRequestParams()->getAttachmentOrNull(
            RequestParams::PARAM_TYPE_BODY,
            self::PARAMETER_SIGNED_DOCUMENT
        );

        if (is_null($attachment)) {
            throw $this->getBadRequestException('Signed document is required');
        }

        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO ohrm_innerstudios_hire_document
                (candidate_id, file_name, file_type, file_size, file_content, uploaded_at)
             VALUES (:candidateId, :fileName, :fileType, :fileSize, :fileContent, NOW())
             ON DUPLICATE KEY UPDATE
                file_name = VALUES(file_name),
                file_type = VALUES(file_type),
                file_size = VALUES(file_size),
                file_content = VALUES(file_content),
                uploaded_at = NOW()',
            [
                'candidateId' => $candidateVacancy->getCandidate()->getId(),
                'fileName' => $attachment->getFilename(),
                'fileType' => $attachment->getFileType(),
                'fileSize' => $attachment->getSize(),
                'fileContent' => $attachment->getContent(),
            ]
        );
    }

    private function ensureInnerStudiosTables(): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS ohrm_innerstudios_recruitment_offer (
                candidate_id INT NOT NULL,
                work_shift_id INT NULL,
                worker_decides TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (candidate_id)
            )'
        );
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS ohrm_innerstudios_hire_document (
                candidate_id INT NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_type VARCHAR(255) NULL,
                file_size INT NULL,
                file_content LONGBLOB NOT NULL,
                uploaded_at DATETIME NOT NULL,
                PRIMARY KEY (candidate_id)
            )'
        );
    }
}
