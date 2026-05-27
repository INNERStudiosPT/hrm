<?php

namespace OrangeHRM\Recruitment\Controller;

use OrangeHRM\Core\Controller\AbstractController;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\Http\Response;

class InnerStudiosRecruitmentTemplatesApiController extends AbstractController
{
    use EntityManagerHelperTrait;

    public function handle(Request $request): Response
    {
        $this->ensureTemplateTable();

        if ($request->getMethod() === 'POST') {
            return $this->saveTemplate($request);
        }

        $template = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT id, file_name, file_type, file_size, updated_at
             FROM ohrm_innerstudios_recruitment_template
             ORDER BY id DESC
             LIMIT 1'
        );

        return $this->json(['data' => $template ?: null]);
    }

    private function saveTemplate(Request $request): Response
    {
        $payload = json_decode((string)$request->getContent(), true);
        $file = $payload['template'] ?? null;
        if (
            !is_array($file) ||
            empty($file['name']) ||
            empty($file['type']) ||
            empty($file['base64']) ||
            !isset($file['size'])
        ) {
            return $this->json(['error' => ['message' => 'Template em falta.']], Response::HTTP_BAD_REQUEST);
        }

        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO ohrm_innerstudios_recruitment_template
                (file_name, file_type, file_size, file_content, updated_at)
             VALUES (:fileName, :fileType, :fileSize, :fileContent, NOW())',
            [
                'fileName' => $file['name'],
                'fileType' => $file['type'],
                'fileSize' => (int)$file['size'],
                'fileContent' => base64_decode($file['base64']),
            ]
        );

        return $this->json(['data' => ['fileName' => $file['name']]]);
    }

    private function ensureTemplateTable(): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'CREATE TABLE IF NOT EXISTS ohrm_innerstudios_recruitment_template (
                id INT AUTO_INCREMENT NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_type VARCHAR(255) NULL,
                file_size INT NULL,
                file_content LONGBLOB NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id)
            )'
        );
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
