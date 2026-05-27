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
use OrangeHRM\Entity\User;
use OrangeHRM\Framework\Http\Request;
use Throwable;

class InnerStudiosSsoService
{
    use UserServiceTrait;

    private const SESSION_COOKIE = 'innercircle_session';
    private const PROFILE_URL = 'https://api.innerstudios.pt/v1/auth/me';

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
        if (!isset($profile['avatar_url']) || !is_string($profile['avatar_url'])) {
            return null;
        }

        $avatarUrl = trim($profile['avatar_url']);
        return filter_var($avatarUrl, FILTER_VALIDATE_URL) ? $avatarUrl : null;
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
