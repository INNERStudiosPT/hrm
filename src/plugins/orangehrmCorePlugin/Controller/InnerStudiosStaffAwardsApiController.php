<?php

namespace OrangeHRM\Core\Controller;

use GuzzleHttp\Client;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\Http\Response;
use Throwable;

class InnerStudiosStaffAwardsApiController extends AbstractController
{
    private const API_BASE_URL = 'https://api.innerstudios.pt';
    private const STAFF_AWARDS_PATH = '/v1/public/staff-awards';

    public function handle(Request $request): Response
    {
        $client = new Client(['timeout' => 8, 'http_errors' => false]);

        try {
            $apiResponse = $client->get(
                self::API_BASE_URL . self::STAFF_AWARDS_PATH,
                ['headers' => ['Accept' => 'application/json']]
            );
        } catch (Throwable $e) {
            return $this->json(['week' => null, 'month' => null, 'meta' => ['unavailable' => true]]);
        }

        $status = $apiResponse->getStatusCode();
        if ($status < 200 || $status >= 300) {
            // Avoid surfacing upstream 404s to the browser (noise in devtools).
            return $this->json(['week' => null, 'month' => null, 'meta' => ['upstream_status' => $status]]);
        }

        $payload = json_decode((string)$apiResponse->getBody(), true);
        if (!is_array($payload)) {
            return $this->json(['week' => null, 'month' => null, 'meta' => ['invalid_payload' => true]]);
        }

        // Pass through known fields; treat everything else as non-critical.
        $week = isset($payload['week']) && is_array($payload['week']) ? $payload['week'] : null;
        $month = isset($payload['month']) && is_array($payload['month']) ? $payload['month'] : null;

        return $this->json(['week' => $week, 'month' => $month]);
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

