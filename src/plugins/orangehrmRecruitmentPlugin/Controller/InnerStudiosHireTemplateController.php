<?php

namespace OrangeHRM\Recruitment\Controller;

use OrangeHRM\Core\Controller\AbstractController;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\Http\Response;

class InnerStudiosHireTemplateController extends AbstractController
{
    use EntityManagerHelperTrait;

    public function handle(Request $request): Response
    {
        $this->ensureTemplateTable();
        $template = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT file_name, file_type, file_content
             FROM ohrm_innerstudios_recruitment_template
             ORDER BY id DESC
             LIMIT 1'
        );

        $response = $this->getResponse();
        if (!$template) {
            $response->setContent("INNER Studios HR\n\nTemplate de contrato ainda nao configurado.");
            $response->headers->set('Content-Type', 'text/plain');
            $response->headers->set('Content-Disposition', 'attachment; filename="innerstudios-hr-template.txt"');
            return $response;
        }

        $fileName = $template['file_name'] ?: 'innerstudios-hr-template';
        $response->setContent($template['file_content']);
        $response->headers->set('Content-Type', $template['file_type'] ?: 'application/octet-stream');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', addslashes($fileName)));
        return $response;
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
}
