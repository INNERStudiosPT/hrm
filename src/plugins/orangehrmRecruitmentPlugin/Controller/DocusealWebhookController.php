<?php

namespace OrangeHRM\Recruitment\Controller;

use OrangeHRM\Core\Controller\AbstractController;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\Http\Response;
use OrangeHRM\Recruitment\Service\InnerStudiosRecruitmentPortalService;

class DocusealWebhookController extends AbstractController
{
    use EntityManagerHelperTrait;

    public function handle(Request $request): Response
    {
        $content = $request->getContent();
        $payload = json_decode((string)$content, true);

        if (empty($payload['event_type']) || empty($payload['data'])) {
            return $this->jsonResponse(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $eventType = $payload['event_type'];
        $eventData = $payload['data'];
        $email = $eventData['email'] ?? null;
        $template = $eventData['template'] ?? null;
        $templateName = $template['name'] ?? '';

        if (empty($email)) {
            // Check submission values if email is not directly on top
            if (!empty($eventData['values'])) {
                foreach ($eventData['values'] as $val) {
                    if (isset($val['field']) && stripos($val['field'], 'email') !== false && !empty($val['value'])) {
                        $email = trim($val['value']);
                        break;
                    }
                }
            }
        }

        if (empty($email)) {
            return $this->jsonResponse(['message' => 'No email found in payload, event ignored'], Response::HTTP_OK);
        }

        // Find candidate
        $connection = $this->getEntityManager()->getConnection();
        $candidate = $connection->fetchAssociative(
            'SELECT id, first_name, last_name FROM ohrm_job_candidate WHERE email = :email ORDER BY id DESC LIMIT 1',
            ['email' => $email]
        );

        if (!$candidate) {
            return $this->jsonResponse(['message' => 'Candidate not found for email: ' . $email], Response::HTTP_OK);
        }

        $candidateId = (int)$candidate['id'];

        // Retrieve vacancy context
        $candidateVacancy = $connection->fetchAssociative(
            'SELECT id, vacancy_id FROM ohrm_job_candidate_vacancy WHERE candidate_id = :candidateId ORDER BY id DESC LIMIT 1',
            ['candidateId' => $candidateId]
        );
        $vacancyId = $candidateVacancy ? (int)$candidateVacancy['vacancy_id'] : null;

        // Ensure onboarding row exists
        $connection->executeStatement(
            'INSERT INTO ohrm_innerstudios_candidate_onboarding (candidate_id, vacancy_id, created_at, updated_at)
             VALUES (:candidateId, :vacancyId, NOW(), NOW())
             ON DUPLICATE KEY UPDATE updated_at = NOW()',
            ['candidateId' => $candidateId, 'vacancyId' => $vacancyId]
        );

        $portalService = new InnerStudiosRecruitmentPortalService();

        // 1. Check if the template is the Offer Letter ("Carta Oferta")
        if (stripos($templateName, 'Carta Oferta') !== false || stripos($templateName, 'Offer') !== false) {
            if ($eventType === 'form.completed' || $eventType === 'submission.completed') {
                $status = $eventData['status'] ?? '';
                // If it is completed by the candidate but still has pending signers (e.g. manager), mark it as pending_manager
                if ($status === 'completed') {
                    $connection->executeStatement(
                        "INSERT INTO ohrm_innerstudios_recruitment_offer (candidate_id, offer_letter_status, offer_letter_submission_id, updated_at)
                         VALUES (:candidateId, 'completed', :subId, NOW())
                         ON DUPLICATE KEY UPDATE offer_letter_status = 'completed', offer_letter_submission_id = :subId, updated_at = NOW()",
                        ['candidateId' => $candidateId, 'subId' => $eventData['submission_id'] ?? null]
                    );

                    // Once the Offer Letter is fully completed/signed, send the candidate the contract signing link (Step 2)
                    $portalService->sendContractLink($candidateId);
                } else {
                    $connection->executeStatement(
                        "INSERT INTO ohrm_innerstudios_recruitment_offer (candidate_id, offer_letter_status, offer_letter_submission_id, updated_at)
                         VALUES (:candidateId, 'pending_manager', :subId, NOW())
                         ON DUPLICATE KEY UPDATE offer_letter_status = 'pending_manager', offer_letter_submission_id = :subId, updated_at = NOW()",
                        ['candidateId' => $candidateId, 'subId' => $eventData['submission_id'] ?? null]
                    );
                }
            } elseif ($eventType === 'form.declined' || $eventType === 'submission.expired') {
                $connection->executeStatement(
                    "INSERT INTO ohrm_innerstudios_recruitment_offer (candidate_id, offer_letter_status, updated_at)
                     VALUES (:candidateId, 'declined', NOW())
                     ON DUPLICATE KEY UPDATE offer_letter_status = 'declined', updated_at = NOW()",
                    ['candidateId' => $candidateId]
                );
            }
        }

        // 2. Check if the template is the Agreement/Contract ("Estágios", "Acordo" or "Contrato")
        if (stripos($templateName, 'Estágios') !== false || stripos($templateName, 'Acordo') !== false || stripos($templateName, 'Contrato') !== false) {
            if ($eventType === 'form.completed' || $eventType === 'submission.completed') {
                // Download and save the signed document to ohrm_innerstudios_hire_document
                $document = $eventData['documents'][0] ?? null;
                if ($document && !empty($document['url'])) {
                    try {
                        $fileContent = file_get_contents($document['url']);
                        if ($fileContent) {
                            $fileName = $document['name'] ?? 'Contrato_Assinado.pdf';
                            $fileType = 'application/pdf';
                            $fileSize = strlen($fileContent);

                            $connection->executeStatement(
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
                                    'candidateId' => $candidateId,
                                    'fileName' => $fileName,
                                    'fileType' => $fileType,
                                    'fileSize' => $fileSize,
                                    'fileContent' => $fileContent,
                                ]
                            );
                        }
                    } catch (\Exception $e) {
                        // Log errors silently
                    }
                }

                $connection->executeStatement(
                    'UPDATE ohrm_innerstudios_candidate_onboarding
                     SET contract_uploaded_at = NOW(), updated_at = NOW()
                     WHERE candidate_id = :candidateId',
                    ['candidateId' => $candidateId]
                );
            }
        }

        return $this->jsonResponse(['status' => 'success'], Response::HTTP_OK);
    }

    private function jsonResponse(array $payload, int $status = Response::HTTP_OK): Response
    {
        $response = $this->getResponse();
        $response->setStatusCode($status);
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($payload, JSON_UNESCAPED_SLASHES));
        return $response;
    }
}
