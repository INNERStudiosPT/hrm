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

namespace OrangeHRM\Pim\Api;

use DateTime;
use OrangeHRM\Core\Api\CommonParams;
use OrangeHRM\Core\Api\V2\CrudEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointCollectionResult;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
use OrangeHRM\Core\Api\V2\ParameterBag;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Core\Traits\UserRoleManagerTrait;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\EmployeeWorkScheduleConfig;
use OrangeHRM\Entity\EmployeeWorkShift;
use OrangeHRM\Entity\WorkScheduleRequest;
use OrangeHRM\Entity\WorkShift;
use OrangeHRM\Pim\Api\Model\EmployeeWorkingScheduleModel;
use OrangeHRM\Pim\Traits\Service\EmployeeServiceTrait;

class EmployeeWorkingScheduleAPI extends Endpoint implements CrudEndpoint
{
    use EmployeeServiceTrait;
    use EntityManagerHelperTrait;
    use UserRoleManagerTrait;
    use AuthUserTrait;

    /**
     * GET /api/v2/pim/employees/{empNumber}/working-schedule
     *
     * @inheritDoc
     */
    public function getOne(): EndpointResourceResult
    {
        $empNumber = $this->getRequestParams()->getInt(
            RequestParams::PARAM_TYPE_ATTRIBUTE,
            CommonParams::PARAMETER_EMP_NUMBER
        );

        $isSelf = $this->getUserRoleManagerHelper()->isSelfByEmpNumber($empNumber);
        $isAdmin = $this->getAuthUser()->getUserRoleName() === 'Admin';

        if (!$isSelf && !$isAdmin) {
            throw $this->getAccessDeniedException();
        }

        $employee = $this->getEmployeeService()->getEmployeeByEmpNumber($empNumber);
        $this->throwRecordNotFoundExceptionIfNotExist($employee, Employee::class);

        // 1. Get configuration
        $config = $this->getEntityManager()
            ->getRepository(EmployeeWorkScheduleConfig::class)
            ->findOneBy(['empNumber' => $empNumber]);
        $isCustomizable = $config ? $config->isCustomizable() : false;

        // 2. Get current shift
        $currentShiftData = null;
        $empWorkShift = $this->getEntityManager()
            ->getRepository(EmployeeWorkShift::class)
            ->findOneBy(['employee' => $empNumber]);
        if ($empWorkShift instanceof EmployeeWorkShift) {
            $shift = $empWorkShift->getWorkShift();
            $currentShiftData = [
                'id' => $shift->getId(),
                'name' => $shift->getName(),
                'startTime' => $shift->getStartTime()->format('H:i'),
                'endTime' => $shift->getEndTime()->format('H:i'),
                'hoursPerDay' => (float)$shift->getHoursPerDay(),
            ];
        }

        // 3. Get available shifts
        $availableShiftsData = [];
        $shifts = $this->getEntityManager()
            ->getRepository(WorkShift::class)
            ->findAll();
        foreach ($shifts as $shift) {
            $availableShiftsData[] = [
                'id' => $shift->getId(),
                'name' => $shift->getName(),
                'startTime' => $shift->getStartTime()->format('H:i'),
                'endTime' => $shift->getEndTime()->format('H:i'),
                'hoursPerDay' => (float)$shift->getHoursPerDay(),
            ];
        }

        // 4. Get pending/latest request
        $pendingRequestData = null;
        $pendingRequest = $this->getEntityManager()
            ->getRepository(WorkScheduleRequest::class)
            ->findOneBy(['employee' => $empNumber, 'status' => 'pending'], ['id' => 'DESC']);
        if ($pendingRequest instanceof WorkScheduleRequest) {
            $shift = $pendingRequest->getWorkShift();
            $pendingRequestData = [
                'id' => $pendingRequest->getId(),
                'status' => $pendingRequest->getStatus(),
                'reason' => $pendingRequest->getReason(),
                'createdAt' => $pendingRequest->getCreatedAt()->format('Y-m-d H:i:s'),
                'workShift' => [
                    'id' => $shift->getId(),
                    'name' => $shift->getName(),
                    'startTime' => $shift->getStartTime()->format('H:i'),
                    'endTime' => $shift->getEndTime()->format('H:i'),
                    'hoursPerDay' => (float)$shift->getHoursPerDay(),
                ]
            ];
        }

        $resultData = [
            'isSelf' => $isSelf,
            'isAdmin' => $isAdmin,
            'isCustomizable' => $isCustomizable,
            'currentShift' => $currentShiftData,
            'availableShifts' => $availableShiftsData,
            'pendingRequest' => $pendingRequestData,
        ];

        return new EndpointResourceResult(
            EmployeeWorkingScheduleModel::class,
            $resultData,
            new ParameterBag([CommonParams::PARAMETER_EMP_NUMBER => $empNumber])
        );
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForGetOne(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule(
                CommonParams::PARAMETER_EMP_NUMBER,
                new Rule(Rules::IN_ACCESSIBLE_EMP_NUMBERS)
            )
        );
    }

    /**
     * POST /api/v2/pim/employees/{empNumber}/working-schedule/request
     * Submit work schedule change request (Employee only, if customizable)
     *
     * @inheritDoc
     */
    public function create(): EndpointResult
    {
        $empNumber = $this->getRequestParams()->getInt(
            RequestParams::PARAM_TYPE_ATTRIBUTE,
            CommonParams::PARAMETER_EMP_NUMBER
        );

        $isSelf = $this->getUserRoleManagerHelper()->isSelfByEmpNumber($empNumber);
        if (!$isSelf) {
            throw $this->getAccessDeniedException();
        }

        $employee = $this->getEmployeeService()->getEmployeeByEmpNumber($empNumber);
        $this->throwRecordNotFoundExceptionIfNotExist($employee, Employee::class);

        // Check config customizable
        $config = $this->getEntityManager()
            ->getRepository(EmployeeWorkScheduleConfig::class)
            ->findOneBy(['empNumber' => $empNumber]);
        $isCustomizable = $config ? $config->isCustomizable() : false;

        if (!$isCustomizable) {
            throw $this->getAccessDeniedException();
        }

        // Check if there is already a pending request
        $existingPending = $this->getEntityManager()
            ->getRepository(WorkScheduleRequest::class)
            ->findOneBy(['employee' => $empNumber, 'status' => 'pending']);
        if ($existingPending instanceof WorkScheduleRequest) {
            throw $this->getForbiddenException(); // Cannot submit multiple pending requests
        }

        $workShiftId = $this->getRequestParams()->getInt(
            RequestParams::PARAM_TYPE_BODY,
            'workShiftId'
        );
        $reason = $this->getRequestParams()->getStringOrNull(
            RequestParams::PARAM_TYPE_BODY,
            'reason'
        );

        $workShift = $this->getEntityManager()
            ->getRepository(WorkShift::class)
            ->find($workShiftId);
        $this->throwRecordNotFoundExceptionIfNotExist($workShift, WorkShift::class);

        $request = new WorkScheduleRequest();
        $request->setEmployee($employee);
        $request->setWorkShift($workShift);
        $request->setReason($reason);
        $request->setStatus('pending');
        $request->setCreatedAt(new DateTime());

        $this->getEntityManager()->persist($request);
        $this->getEntityManager()->flush();

        return new EndpointResourceResult(
            EmployeeWorkingScheduleModel::class,
            ['id' => $request->getId(), 'status' => 'pending'],
            new ParameterBag([CommonParams::PARAMETER_EMP_NUMBER => $empNumber])
        );
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule(CommonParams::PARAMETER_EMP_NUMBER, new Rule(Rules::IN_ACCESSIBLE_EMP_NUMBERS)),
            new ParamRule('workShiftId', new Rule(Rules::POSITIVE)),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule('reason', new Rule(Rules::STRING_TYPE))
            )
        );
    }

    /**
     * PUT /api/v2/pim/employees/{empNumber}/working-schedule/config
     * PUT /api/v2/pim/employees/{empNumber}/working-schedule/request/{requestId}
     *
     * @inheritDoc
     */
    public function update(): EndpointResourceResult
    {
        $empNumber = $this->getRequestParams()->getInt(
            RequestParams::PARAM_TYPE_ATTRIBUTE,
            CommonParams::PARAMETER_EMP_NUMBER
        );

        $isAdmin = $this->getAuthUser()->getUserRoleName() === 'Admin';
        if (!$isAdmin) {
            throw $this->getAccessDeniedException();
        }

        $employee = $this->getEmployeeService()->getEmployeeByEmpNumber($empNumber);
        $this->throwRecordNotFoundExceptionIfNotExist($employee, Employee::class);

        $requestId = $this->getRequestParams()->getIntOrNull(
            RequestParams::PARAM_TYPE_ATTRIBUTE,
            'requestId'
        );

        if ($requestId !== null) {
            // Flow A: Resolve Request
            $request = $this->getEntityManager()
                ->getRepository(WorkScheduleRequest::class)
                ->find($requestId);
            $this->throwRecordNotFoundExceptionIfNotExist($request, WorkScheduleRequest::class);

            if ($request->getEmployee()->getEmpNumber() !== $empNumber) {
                throw $this->getAccessDeniedException();
            }

            if ($request->getStatus() !== 'pending') {
                throw $this->getForbiddenException();
            }

            $status = $this->getRequestParams()->getString(
                RequestParams::PARAM_TYPE_BODY,
                'status'
            );

            $request->setStatus($status);
            $this->getEntityManager()->persist($request);

            if ($status === 'approved') {
                // Apply turn/shift change to native employee work shift mapping
                $workShift = $request->getWorkShift();

                // Delete existing work shifts for this employee
                $existingShifts = $this->getEntityManager()
                    ->getRepository(EmployeeWorkShift::class)
                    ->findBy(['employee' => $employee]);
                foreach ($existingShifts as $existingShift) {
                    $this->getEntityManager()->remove($existingShift);
                }
                $this->getEntityManager()->flush();

                // Persist new shift association
                $empWorkShift = new EmployeeWorkShift();
                $empWorkShift->setEmployee($employee);
                $empWorkShift->setWorkShift($workShift);
                $this->getEntityManager()->persist($empWorkShift);
            }

            $this->getEntityManager()->flush();

            return new EndpointResourceResult(
                EmployeeWorkingScheduleModel::class,
                ['id' => $requestId, 'status' => $status],
                new ParameterBag([CommonParams::PARAMETER_EMP_NUMBER => $empNumber])
            );

        } else {
            // Flow B: Update Config
            $isCustomizable = $this->getRequestParams()->getBoolean(
                RequestParams::PARAM_TYPE_BODY,
                'isCustomizable'
            );

            $config = $this->getEntityManager()
                ->getRepository(EmployeeWorkScheduleConfig::class)
                ->findOneBy(['empNumber' => $empNumber]);

            if (!$config instanceof EmployeeWorkScheduleConfig) {
                $config = new EmployeeWorkScheduleConfig();
                $config->setEmployee($employee);
            }

            $config->setIsCustomizable($isCustomizable);
            $this->getEntityManager()->persist($config);
            $this->getEntityManager()->flush();

            return new EndpointResourceResult(
                EmployeeWorkingScheduleModel::class,
                ['isCustomizable' => $isCustomizable],
                new ParameterBag([CommonParams::PARAMETER_EMP_NUMBER => $empNumber])
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForUpdate(): ParamRuleCollection
    {
        $requestId = $this->getRequestParams()->getIntOrNull(
            RequestParams::PARAM_TYPE_ATTRIBUTE,
            'requestId'
        );

        if ($requestId !== null) {
            return new ParamRuleCollection(
                new ParamRule(CommonParams::PARAMETER_EMP_NUMBER, new Rule(Rules::IN_ACCESSIBLE_EMP_NUMBERS)),
                new ParamRule('requestId', new Rule(Rules::POSITIVE)),
                new ParamRule('status', new Rule(Rules::IN, [['approved', 'rejected']]))
            );
        } else {
            return new ParamRuleCollection(
                new ParamRule(CommonParams::PARAMETER_EMP_NUMBER, new Rule(Rules::IN_ACCESSIBLE_EMP_NUMBERS)),
                new ParamRule('isCustomizable', new Rule(Rules::BOOLEAN_TYPE))
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(): EndpointResult
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getAll(): EndpointCollectionResult
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }
}
