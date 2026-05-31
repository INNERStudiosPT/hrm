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

namespace OrangeHRM\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="ohrm_employee_work_schedule_config")
 * @ORM\Entity
 */
class EmployeeWorkScheduleConfig
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(name="emp_number", type="integer")
     */
    private int $empNumber;

    /**
     * @var Employee
     *
     * @ORM\OneToOne(targetEntity="OrangeHRM\Entity\Employee")
     * @ORM\JoinColumn(name="emp_number", referencedColumnName="emp_number", onDelete="CASCADE")
     */
    private Employee $employee;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_customizable", type="boolean")
     */
    private bool $isCustomizable = false;

    /**
     * @return int
     */
    public function getEmpNumber(): int
    {
        return $this->empNumber;
    }

    /**
     * @param int $empNumber
     */
    public function setEmpNumber(int $empNumber): void
    {
        $this->empNumber = $empNumber;
    }

    /**
     * @return Employee
     */
    public function getEmployee(): Employee
    {
        return $this->employee;
    }

    /**
     * @param Employee $employee
     */
    public function setEmployee(Employee $employee): void
    {
        $this->employee = $employee;
        $this->empNumber = $employee->getEmpNumber();
    }

    /**
     * @return bool
     */
    public function isCustomizable(): bool
    {
        return $this->isCustomizable;
    }

    /**
     * @param bool $isCustomizable
     */
    public function setIsCustomizable(bool $isCustomizable): void
    {
        $this->isCustomizable = $isCustomizable;
    }
}
