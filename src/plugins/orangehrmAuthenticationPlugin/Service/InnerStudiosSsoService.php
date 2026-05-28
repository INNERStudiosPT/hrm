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

namespace OrangeHRM\Authentication\Service;

use GuzzleHttp\Client;
use OrangeHRM\Admin\Traits\Service\UserServiceTrait;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\User;
use OrangeHRM\Framework\Http\Request;
use Throwable;

class InnerStudiosSsoService
{
    use UserServiceTrait;

    private const SESSION_COOKIE = 'innercircle_session';
    private const PROFILE_URL = 'https://api.innerstudios.pt/v1/auth/me';
    private const USER_LOOKUP_PATHS = [
        '/v1/users/lookup',
        '/v1/auth/users/lookup',
        '/v1/users/search',
        '/v1/auth/users/search',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function getProfile(Request $request): ?array
    {
        $token = $request->cookies->get(self::SESSION_COOKIE);
        if (!is_string($token) || trim($token) === '') {
            return null;
        }

        try {
            $response = (new Client(['timeout' => 5, 'http_errors' => false]))->get(
                self::PROFILE_URL,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Cookie' => self::SESSION_COOKIE . '=' . $token,
                    ],
                ]
            );
        } catch (Throwable $e) {
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $payload = json_decode((string)$response->getBody(), true);
        if (!is_array($payload)) {
            return null;
        }

        return isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function findMatchingUser(array $profile): ?User
    {
        $email = $this->getProfileEmail($profile);
        if ($email !== null) {
            $user = $this->getUserService()->geUserDao()->getUserByEmployeeEmail($email);
            if ($user instanceof User && !$user->isDeleted()) {
                return $user;
            }
        }

        foreach ($this->getCandidateUsernames($profile) as $username) {
            $user = $this->getUserService()->geUserDao()->getUserByUserName($username);
            if ($user instanceof User && !$user->isDeleted()) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function getAvatarUrl(array $profile): ?string
    {
        foreach (['avatar_url', 'avatarUrl', 'picture', 'image', 'photo_url', 'profile_picture'] as $field) {
            if (!isset($profile[$field]) || !is_string($profile[$field])) {
                continue;
            }

            $avatarUrl = trim($profile[$field]);
            if (filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
                return $avatarUrl;
            }
        }

        return null;
    }

    public function getAvatarUrlForEmployee(Request $request, Employee $employee): ?string
    {
        $token = $request->cookies->get(self::SESSION_COOKIE);
        if (!is_string($token) || trim($token) === '') {
            return null;
        }

        $currentProfile = $this->getProfile($request);
        if (is_array($currentProfile) && $this->profileMatchesEmployee($currentProfile, $employee)) {
            return $this->getAvatarUrl($currentProfile);
        }

        foreach ($this->getEmployeeLookupValues($employee) as $lookup) {
            $profile = $this->lookupProfile($token, $lookup);
            if (is_array($profile)) {
                $avatarUrl = $this->getAvatarUrl($profile);
                if ($avatarUrl !== null) {
                    return $avatarUrl;
                }
            }
        }

        return null;
    }

    private function lookupProfile(string $token, string $lookup): ?array
    {
        $client = new Client(['timeout' => 5, 'http_errors' => false]);
        foreach (self::USER_LOOKUP_PATHS as $path) {
            try {
                $response = $client->get(
                    'https://api.innerstudios.pt' . $path,
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Cookie' => self::SESSION_COOKIE . '=' . $token,
                        ],
                        'query' => [
                            'q' => $lookup,
                            'email' => $lookup,
                            'username' => $lookup,
                        ],
                    ]
                );
            } catch (Throwable $e) {
                continue;
            }

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                continue;
            }

            $payload = json_decode((string)$response->getBody(), true);
            if (!is_array($payload)) {
                continue;
            }

            $profile = $this->extractProfileFromLookupPayload($payload, $lookup);
            if (is_array($profile)) {
                return $profile;
            }
        }

        return null;
    }

    private function extractProfileFromLookupPayload(array $payload, string $lookup): ?array
    {
        $items = $payload['data'] ?? $payload['users'] ?? $payload['items'] ?? $payload['results'] ?? $payload;
        if (isset($items['email']) || isset($items['username']) || isset($items['avatar_url'])) {
            return $items;
        }

        if (!is_array($items)) {
            return null;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $email = isset($item['email']) && is_string($item['email']) ? strtolower($item['email']) : null;
            $username = isset($item['username']) && is_string($item['username']) ? strtolower($item['username']) : null;
            if ($email === strtolower($lookup) || $username === strtolower($lookup)) {
                return $item;
            }
        }

        return null;
    }

    private function profileMatchesEmployee(array $profile, Employee $employee): bool
    {
        $profileEmail = $this->getProfileEmail($profile);
        if ($profileEmail !== null) {
            foreach ([$employee->getWorkEmail(), $employee->getOtherEmail()] as $email) {
                if (is_string($email) && strtolower(trim($email)) === $profileEmail) {
                    return true;
                }
            }
        }

        $user = $this->getUserService()->geUserDao()->getUserByEmpNumber($employee->getEmpNumber());
        return $user instanceof User
            && isset($profile['username'])
            && is_string($profile['username'])
            && strtolower($user->getUserName()) === strtolower(trim($profile['username']));
    }

    /**
     * @return string[]
     */
    private function getEmployeeLookupValues(Employee $employee): array
    {
        $values = [];
        foreach ([$employee->getWorkEmail(), $employee->getOtherEmail()] as $email) {
            if (is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                $values[] = strtolower(trim($email));
            }
        }

        $user = $this->getUserService()->geUserDao()->getUserByEmpNumber($employee->getEmpNumber());
        if ($user instanceof User && trim($user->getUserName()) !== '') {
            $values[] = strtolower(trim($user->getUserName()));
        }

        return array_values(array_unique($values));
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function getProfileEmail(array $profile): ?string
    {
        if (!isset($profile['email']) || !is_string($profile['email'])) {
            return null;
        }

        $email = strtolower(trim($profile['email']));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @param array<string, mixed> $profile
     * @return string[]
     */
    private function getCandidateUsernames(array $profile): array
    {
        $candidates = [];
        foreach (['username', 'email', 'display_name'] as $field) {
            if (!isset($profile[$field]) || !is_string($profile[$field])) {
                continue;
            }

            $value = trim($profile[$field]);
            if ($field === 'email' && strpos($value, '@') !== false) {
                $value = substr($value, 0, strpos($value, '@'));
            }

            $normalized = preg_replace('/[^a-zA-Z0-9._-]/', '', strtolower($value));
            if (is_string($normalized) && $normalized !== '') {
                $candidates[] = $normalized;
            }
        }

        return array_values(array_unique($candidates));
    }
}
