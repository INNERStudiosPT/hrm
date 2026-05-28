<?php

namespace OrangeHRM\Recruitment\Service;

use GuzzleHttp\Client;
use OrangeHRM\Core\Traits\LoggerTrait;

class InnerStudiosAgendaClient
{
    use LoggerTrait;

    private const API_BASE_URL = 'https://api.innerstudios.pt';

    /**
     * @return array{ok: bool, slots: array<int, array<string, string>>, error: string|null}
     */
    public function getAvailability(array $context): array
    {
        $paths = [
            '/v1/agenda/availability',
            '/v1/calendar/availability',
            '/v1/bookings/availability',
            '/v1/schedule/availability',
        ];

        foreach ($paths as $path) {
            $result = $this->request('GET', $path, $context);
            if ($result['ok'] && isset($result['payload'])) {
                $slots = $this->normalizeSlots($result['payload']);
                if (!empty($slots)) {
                    return ['ok' => true, 'slots' => $slots, 'error' => null];
                }
            }
        }

        return [
            'ok' => false,
            'slots' => [],
            'error' => 'Não foi possível carregar horários da Agenda InnerStudios.',
        ];
    }

    /**
     * @return array{ok: bool, error: string|null}
     */
    public function createBooking(array $payload): array
    {
        $paths = [
            '/v1/agenda/bookings',
            '/v1/calendar/bookings',
            '/v1/bookings',
            '/v1/schedule/bookings',
        ];

        foreach ($paths as $path) {
            $result = $this->request('POST', $path, $payload);
            if ($result['ok']) {
                return ['ok' => true, 'error' => null];
            }
        }

        return [
            'ok' => false,
            'error' => 'A Agenda InnerStudios não confirmou a marcação.',
        ];
    }

    /**
     * @return array{ok: bool, payload?: array<string, mixed>, error?: string}
     */
    private function request(string $method, string $path, array $payload): array
    {
        try {
            $client = new Client(['http_errors' => false]);
            $url = self::API_BASE_URL . $path;
            $options = [
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 8,
            ];

            if ($method === 'GET') {
                $query = http_build_query($payload);
                if ($query !== '') {
                    $url .= '?' . $query;
                }
                $response = $client->get($url, $options);
            } else {
                $options['body'] = json_encode($payload);
                $response = $client->post($url, $options);
            }

            $status = $response->getStatusCode();
            $content = (string)$response->getBody();
            $decoded = json_decode($content, true);

            return [
                'ok' => $status >= 200 && $status < 300 && is_array($decoded),
                'payload' => is_array($decoded) ? $decoded : [],
            ];
        } catch (\Throwable $e) {
            $this->getLogger()->warning('InnerStudios Agenda request failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, string>>
     */
    private function normalizeSlots(array $payload): array
    {
        $items = $payload['data'] ?? $payload['slots'] ?? $payload['availability'] ?? $payload['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $slots = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $date = $item['date'] ?? $item['day'] ?? null;
            $time = $item['time'] ?? $item['startTime'] ?? $item['start_time'] ?? null;
            if (is_string($date) && is_string($time)) {
                $slots[] = ['date' => substr($date, 0, 10), 'time' => substr($time, 0, 5)];
            }
        }

        return $slots;
    }
}
