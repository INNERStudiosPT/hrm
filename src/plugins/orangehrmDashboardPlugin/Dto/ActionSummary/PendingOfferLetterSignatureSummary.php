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

namespace OrangeHRM\Dashboard\Dto\ActionSummary;

use OrangeHRM\Dashboard\Traits\Service\EmployeeActionSummaryServiceTrait;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;

class PendingOfferLetterSignatureSummary implements ActionSummary
{
    use EmployeeActionSummaryServiceTrait;
    use EntityManagerHelperTrait;

    /**
     * @var int
     */
    private int $managerEmpNumber;

    /**
     * @param int $managerEmpNumber
     */
    public function __construct(int $managerEmpNumber)
    {
        $this->managerEmpNumber = $managerEmpNumber;
    }

    /**
     * @return int
     */
    public function getGroupId(): int
    {
        return 6;
    }

    /**
     * @return string
     */
    public function getGroup(): string
    {
        return 'Offer Letters To Sign';
    }

    /**
     * @inheritDoc
     */
    public function getPendingActionCount(): int
    {
        try {
            $connection = $this->getEntityManager()->getConnection();

            return (int)$connection->fetchOne(
                "SELECT COUNT(DISTINCT o.candidate_id)
                 FROM ohrm_innerstudios_recruitment_offer o
                 INNER JOIN ohrm_job_candidate_vacancy cv ON cv.candidate_id = o.candidate_id
                 INNER JOIN ohrm_job_vacancy v ON v.id = cv.vacancy_id
                 WHERE v.hiring_manager_id = :managerEmpNumber
                   AND o.offer_letter_status = 'pending_manager'",
                ['managerEmpNumber' => $this->managerEmpNumber]
            );
        } catch (\Exception $e) {
            return 0;
        }
    }
}
