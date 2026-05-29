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

    private static ?array $requestProfileCache = null;

    /**
     * @return array<string, mixed>|null
     */
    public function getProfile(Request $request): ?array
    {
        if (self::$requestProfileCache !== null) {
            return self::$requestProfileCache;
        }

        $token = $request->cookies->get(self::SESSION_COOKIE);
        if (!is_string($token) || trim($token) === '') {
            return null;
        }

        // Try to get from session cache
        try {
            $session = \OrangeHRM\Framework\ServiceContainer::getContainer()->get(\OrangeHRM\Framework\Services::SESSION);
            if ($session->has('sso_profile_data')) {
                $cached = $session->get('sso_profile_data');
                if (is_array($cached) && ($cached['__timestamp'] ?? 0) > (time() - 300)) { // 5-minute session cache
                    self::$requestProfileCache = $cached['data'];
                    return self::$requestProfileCache;
                }
            }
        } catch (Throwable $e) {
            // Ignore session errors
        }

        try {
            $response = (new Client(['timeout' => 2, 'http_errors' => false]))->get(
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

        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;

        // Store in session and static cache
        try {
            $session = \OrangeHRM\Framework\ServiceContainer::getContainer()->get(\OrangeHRM\Framework\Services::SESSION);
            $session->set('sso_profile_data', [
                'data' => $data,
                '__timestamp' => time()
            ]);
        } catch (Throwable $e) {
            // Ignore session errors
        }

        self::$requestProfileCache = $data;
        return $data;
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
        $empNumber = $employee->getEmpNumber();
        if ($empNumber === null) {
            return null;
        }

        // Try to get from session cache
        try {
            $session = \OrangeHRM\Framework\ServiceContainer::getContainer()->get(\OrangeHRM\Framework\Services::SESSION);
            if ($session->has('sso_avatar_cache')) {
                $cache = $session->get('sso_avatar_cache');
                if (is_array($cache) && isset($cache[$empNumber])) {
                    $entry = $cache[$empNumber];
                    if (($entry['__timestamp'] ?? 0) > (time() - 3600)) { // 1 hour cache
                        return $entry['url'] !== '' ? $entry['url'] : null;
                    }
                }
            }
        } catch (Throwable $e) {
            // Ignore
        }

        $token = $request->cookies->get(self::SESSION_COOKIE);
        if (!is_string($token) || trim($token) === '') {
            return null;
        }

        $resolvedUrl = null;
        $currentProfile = $this->getProfile($request);
        if (is_array($currentProfile) && $this->profileMatchesEmployee($currentProfile, $employee)) {
            $resolvedUrl = $this->getAvatarUrl($currentProfile);
        } else {
            foreach ($this->getEmployeeLookupValues($employee) as $lookup) {
                $profile = $this->lookupProfile($token, $lookup);
                if (is_array($profile)) {
                    $avatarUrl = $this->getAvatarUrl($profile);
                    if ($avatarUrl !== null) {
                        $resolvedUrl = $avatarUrl;
                        break;
                    }
                }
            }
        }

        // Save to session cache
        try {
            $session = \OrangeHRM\Framework\ServiceContainer::getContainer()->get(\OrangeHRM\Framework\Services::SESSION);
            $cache = $session->get('sso_avatar_cache', []);
            if (!is_array($cache)) {
                $cache = [];
            }
            $cache[$empNumber] = [
                'url' => $resolvedUrl ?? '',
                '__timestamp' => time()
            ];
            $session->set('sso_avatar_cache', $cache);
        } catch (Throwable $e) {
            // Ignore
        }

        return $resolvedUrl;
    }

    /**
     * Synchronize profile data with the user's employee record.
     * Keeps both workEmail and otherEmail if there is an overlap.
     *
     * @param User $user
     * @param array<string, mixed> $profile
     * @return void
     */
    public function syncProfile(User $user, array $profile): void
    {
        $employee = $user->getEmployee();
        if (!$employee instanceof Employee) {
            return;
        }

        $email = $this->getProfileEmail($profile);
        if ($email !== null) {
            $workEmail = $employee->getWorkEmail() ? strtolower(trim($employee->getWorkEmail())) : null;
            $otherEmail = $employee->getOtherEmail() ? strtolower(trim($employee->getOtherEmail())) : null;

            if ($workEmail !== $email && $otherEmail !== $email) {
                if (empty($workEmail)) {
                    $employee->setWorkEmail($email);
                } elseif (empty($otherEmail)) {
                    $employee->setOtherEmail($email);
                } else {
                    // Both are filled and different from the profile email.
                    // Overwrite otherEmail with the old workEmail, and set workEmail to the new profile email.
                    // This preserves both unique emails in the profile!
                    $employee->setOtherEmail($workEmail);
                    $employee->setWorkEmail($email);
                }
            }
        }

        // Sync names
        $firstName = $profile['first_name'] ?? $profile['firstName'] ?? null;
        $lastName = $profile['last_name'] ?? $profile['lastName'] ?? null;

        if ((empty($firstName) || empty($lastName)) && !empty($profile['display_name'])) {
            $parts = explode(' ', trim($profile['display_name']));
            if (empty($firstName)) {
                $firstName = $parts[0];
            }
            if (empty($lastName)) {
                $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $parts[0];
            }
        }

        if (!empty($firstName)) {
            $employee->setFirstName(trim($firstName));
        }
        if (!empty($lastName)) {
            $employee->setLastName(trim($lastName));
        }

        // Persist updates to the database
        $em = \OrangeHRM\Framework\ServiceContainer::getContainer()->get(\OrangeHRM\Framework\Services::DOCTRINE);
        $em->persist($employee);
        $em->flush();
    }

    private function lookupProfile(string $token, string $lookup): ?array
    {
        $client = new Client(['timeout' => 2, 'http_errors' => false]);
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
