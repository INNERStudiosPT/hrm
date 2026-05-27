<?php

namespace OrangeHRM\Core\Controller;

use GuzzleHttp\Client;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\Http\Response;
use Throwable;

class InnerStudiosNotificationsApiController extends AbstractController
{
    private const SESSION_COOKIE = 'innercircle_session';
    private const API_BASE_URL = 'https://api.innerstudios.pt';
    private const NOTIFICATION_PATHS = [
        '/v1/notifications',
        '/v1/me/notifications',
        '/v1/auth/me/notifications',
        '/v1/users/me/notifications',
    ];

    public function handle(Request $request): Response
    {
        $session = $request->cookies->get(self::SESSION_COOKIE);
        if (!is_string($session) || trim($session) === '') {
            return $this->json(
                ['error' => ['message' => 'Sessao InnerStudios em falta.']],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $client = new Client(['timeout' => 8, 'http_errors' => false]);
        $errors = [];

        foreach (self::NOTIFICATION_PATHS as $path) {
            try {
                $apiResponse = $client->get(
                    self::API_BASE_URL . $path,
                    [
                        'headers' => [
                            'Accept' => 'application/json',
                            'Cookie' => self::SESSION_COOKIE . '=' . $session,
                        ],
                    ]
                );
            } catch (Throwable $e) {
                $errors[] = ['path' => $path, 'status' => 0];
                continue;
            }

            $status = $apiResponse->getStatusCode();
            if ($status < 200 || $status >= 300) {
                $errors[] = ['path' => $path, 'status' => $status];
                continue;
            }

            $payload = json_decode((string)$apiResponse->getBody(), true);
            if (!is_array($payload)) {
                return $this->json(['data' => [], 'meta' => ['source' => $path]]);
            }

            return $this->json(
                [
                    'data' => $this->normalizeNotifications($payload),
                    'meta' => ['source' => $path],
                ]
            );
        }

        return $this->json(
            [
                'data' => [],
                'meta' => [
                    'checked' => $errors,
                    'unavailable' => true,
                    'message' => 'Endpoint de notificacoes InnerStudios indisponivel.',
                ],
            ]
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function normalizeNotifications(array $payload): array
    {
        $items = $payload['data'] ?? $payload['notifications'] ?? $payload['items'] ?? $payload['results'] ?? $payload;
        if (!is_array($items)) {
            return [];
        }

        $notifications = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = $this->firstString($item, ['title', 'subject', 'name', 'heading']) ?? 'Notificacao';
            $message = $this->firstString($item, ['message', 'body', 'content', 'description', 'text']) ?? $title;

            $notifications[] = [
                'id' => $this->firstString($item, ['id', 'uuid', 'key']) ?? (string)$index,
                'title' => $title,
                'message' => $message,
                'type' => $this->firstString($item, ['type', 'category', 'level']),
                'createdAt' => $this->firstString($item, ['created_at', 'createdAt', 'date', 'timestamp']),
                'url' => $this->firstString($item, ['url', 'link', 'action_url', 'actionUrl']),
                'read' => $this->readState($item),
            ];
        }

        return $notifications;
    }

    /**
     * @param array<string, mixed> $item
     * @param string[] $keys
     */
    private function firstString(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && (is_string($item[$key]) || is_numeric($item[$key]))) {
                $value = trim((string)$item[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function readState(array $item): bool
    {
        if (array_key_exists('read', $item)) {
            return (bool)$item['read'];
        }

        return !empty($item['read_at']) || !empty($item['readAt']);
    }

    private function json(array $payload, int $status = Response::HTTP_OK): Response
    {
        $response = $this->getResponse();
        $response->setStatusCode($status);
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($payload, JSON_UNESCAPED_SLASHES));
        return $response;
    }
}
