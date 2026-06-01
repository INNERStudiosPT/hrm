<?php

namespace OrangeHRM\Recruitment\Service;

use DateInterval;
use DateTimeImmutable;
use OrangeHRM\Core\Dto\Base64Attachment;
use OrangeHRM\Core\Service\EmailService;
use OrangeHRM\Core\Traits\LoggerTrait;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Entity\CandidateVacancy;
use OrangeHRM\Entity\WorkflowStateMachine;

class InnerStudiosRecruitmentPortalService
{
    use EntityManagerHelperTrait;
    use LoggerTrait;

    public const TOKEN_TYPE_SCHEDULE = 'schedule';
    public const TOKEN_TYPE_DETAILS = 'details';
    public const TOKEN_TYPE_CONTRACT = 'contract';
    public const TOKEN_TYPE_ONBOARDING = 'onboarding';

    private const FROM_EMAIL = 'hr@innerstudios.pt';
    private const FROM_NAME = 'INNER Studios HR';
    private const FALLBACK_BASE_URL = 'https://hrm.innerstudios.pt';
    private const TEAMS_URL = 'https://teams.live.com/l/community/FEAUlDoQq5h-D30Cgo';
    private const DISCORD_URL = 'https://discord.gg/sjJQSMEf';

    private ?EmailService $emailService = null;
    private ?InnerStudiosAgendaClient $agendaClient = null;

    public function sendApplicationConfirmation(CandidateVacancy $candidateVacancy): void
    {
        $candidate = $candidateVacancy->getCandidate();
        $vacancy = $candidateVacancy->getVacancy();
        $this->sendOnce(
            'candidate:' . $candidate->getId() . ':application-confirmation',
            $candidate->getEmail(),
            'Recebemos a tua candidatura | INNER Studios',
            $this->renderEmail(
                'Candidatura Recebida',
                sprintf(
                    "Olá %s,\n\nAgradecemos o teu interesse em fazer parte da INNER Studios. Confirmamos que recebemos com sucesso a tua candidatura para a vaga de **%s**.\n\n**O que acontece a seguir?**\n1. **Triagem de Currículo:** A nossa equipa de Recursos Humanos irá analisar detalhadamente o teu perfil e portfólio.\n2. **Primeiro Contacto:** Se o teu perfil for compatível com os requisitos da vaga, entraremos em contacto para agendar uma entrevista.\n3. **Fases Seguintes:** O processo inclui uma entrevista técnica/comportamental, recolha de dados para contrato e onboarding.\n\nO nosso tempo médio de resposta é de 3 a 5 dias úteis. Caso tenhas alguma dúvida ou pretendas partilhar informações adicionais, podes contactar a nossa equipa através deste canal.\n\nDesejamos-te a maior sorte no processo de seleção!",
                    $candidate->getFirstName(),
                    $vacancy->getName()
                )
            )
        );
    }

    public function sendInterviewScheduling(CandidateVacancy $candidateVacancy): void
    {
        $candidate = $candidateVacancy->getCandidate();
        $token = $this->createToken(
            self::TOKEN_TYPE_SCHEDULE,
            $candidate->getId(),
            $candidateVacancy->getVacancy()->getId(),
            new DateInterval('P14D')
        );
        $url = $this->buildPublicUrl(self::TOKEN_TYPE_SCHEDULE, $token);

        $this->sendOnce(
            'candidate:' . $candidate->getId() . ':schedule-interview',
            $candidate->getEmail(),
            'Agenda a tua entrevista | INNER Studios',
            $this->renderEmail(
                'Próximo Passo: Entrevista',
                sprintf(
                    "Olá %s,\n\nTemos o prazer de te informar que a tua candidatura avançou para a **fase de entrevista**! Este será um espaço informal de cerca de 30 minutos para nos conhecermos melhor, falar sobre o teu percurso e apresentar-te a INNER Studios.\n\nPara darmos seguimento ao processo, pedimos que escolhas o horário que for mais conveniente para ti no nosso portal de marcação.\n\n**Como marcar?**\nClica no botão abaixo, seleciona uma das datas e horas disponíveis indicadas pelo entrevistador e confirma a tua marcação. O link é válido por 14 dias.",
                    $candidate->getFirstName()
                ),
                'Escolher Horário',
                $url
            )
        );
    }

    public function sendWelcomeDetails(CandidateVacancy $candidateVacancy): void
    {
        $candidate = $candidateVacancy->getCandidate();
        $token = $this->createToken(
            self::TOKEN_TYPE_DETAILS,
            $candidate->getId(),
            $candidateVacancy->getVacancy()->getId(),
            new DateInterval('P14D')
        );
        $url = $this->buildPublicUrl(self::TOKEN_TYPE_DETAILS, $token);

        $this->sendOnce(
            'candidate:' . $candidate->getId() . ':welcome-details',
            $candidate->getEmail(),
            'Bem-vindo à próxima fase! | INNER Studios',
            $this->renderEmail(
                'Submissão de Dados para Onboarding',
                sprintf(
                    "Olá %s,\n\nParabéns! A tua entrevista foi um sucesso e decidimos avançar com a tua contratação. Estamos muito entusiasmados por te ter connosco na INNER Studios!\n\nPara podermos elaborar o teu contrato de trabalho e iniciar o teu processo administrativo de integração, necessitamos que nos forneças alguns dados obrigatórios.\n\n**Que dados precisamos?**\n- Nome completo (para efeitos contratuais)\n- Morada de residência completa\n- Número do Cartão de Cidadão / BI\n- Número de Identificação Fiscal (NIF)\n\nPor favor, clica no botão abaixo para preencheres estes dados de forma totalmente segura no nosso portal público.",
                    $candidate->getFirstName()
                ),
                'Preencher Dados',
                $url
            )
        );
    }

    public function sendContractLink(int $candidateId): bool
    {
        $context = $this->getCandidateContext($candidateId);
        if (empty($context)) {
            return false;
        }

        $offer = $this->getConnection()->fetchAssociative(
            'SELECT offer_type FROM ohrm_innerstudios_recruitment_offer WHERE candidate_id = :candidateId',
            ['candidateId' => $candidateId]
        );
        $offerType = $offer ? $offer['offer_type'] : 'contratacao';

        if ($offerType !== 'estagio' && !$this->hasContractTemplate()) {
            return false;
        }

        $token = $this->createToken(
            self::TOKEN_TYPE_CONTRACT,
            $candidateId,
            (int)$context['vacancy_id'],
            new DateInterval('P14D')
        );
        $url = $this->buildPublicUrl(self::TOKEN_TYPE_CONTRACT, $token);

        if ($offerType === 'estagio') {
            $subject = 'Acordo de Colaboração e Cedência de Direitos disponível para assinatura | INNER Studios';
            $title = 'Acordo de Colaboração e Cedência de Direitos';
            $message = sprintf(
                "Olá %s,\n\nO teu acordo de colaboração e cedência de direitos já está pronto e disponível para assinatura digital!\n\n**Instruções para a assinatura:**\n1. Clica no botão abaixo para acederes ao portal do candidato.\n2. Completa a assinatura digital do acordo de forma segura.\n\nApós a assinatura do acordo por todas as partes, prosseguiremos com os passos de onboarding.",
                (string)$context['first_name']
            );
            $buttonLabel = 'Assinar Acordo';
        } else {
            $subject = 'Contrato de Trabalho disponível para assinatura | INNER Studios';
            $title = 'Contrato de Trabalho';
            $message = sprintf(
                "Olá %s,\n\nO teu contrato de trabalho já está pronto e disponível para assinatura!\n\n**Instruções para a assinatura:**\n1. Clica no botão abaixo para acederes ao portal do candidato.\n2. Descarrega a cópia do teu contrato em PDF.\n3. Assina o contrato (digitalmente com Chave Móvel Digital / Assinatura Digital Certificada ou imprimindo e assinando fisicamente).\n4. Faz o upload da versão assinada (em formato PDF ou imagem nítida) diretamente na mesma página.\n\nApós o envio do contrato assinado, faremos a validação interna.",
                (string)$context['first_name']
            );
            $buttonLabel = 'Aceder ao Contrato';
        }

        $this->sendOnce(
            'candidate:' . $candidateId . ':contract-link',
            (string)$context['email'],
            $subject,
            $this->renderEmail(
                $title,
                $message,
                $buttonLabel,
                $url
            )
        );

        $this->getConnection()->executeStatement(
            'UPDATE ohrm_innerstudios_candidate_onboarding
             SET contract_requested_at = NOW(), updated_at = NOW()
             WHERE candidate_id = :candidateId',
            ['candidateId' => $candidateId]
        );

        return true;
    }

    public function sendOnboarding(CandidateVacancy $candidateVacancy): void
    {
        $candidate = $candidateVacancy->getCandidate();
        $token = $this->createToken(
            self::TOKEN_TYPE_ONBOARDING,
            $candidate->getId(),
            $candidateVacancy->getVacancy()->getId(),
            new DateInterval('P30D')
        );
        $url = $this->buildPublicUrl(self::TOKEN_TYPE_ONBOARDING, $token);

        $this->sendOnce(
            'candidate:' . $candidate->getId() . ':onboarding',
            $candidate->getEmail(),
            'Onboarding INNER Studios HR',
            $this->renderEmail(
                'Bem-vindo à INNER Studios!',
                sprintf(
                    "Olá %s,\n\nEstá tudo pronto para começares a tua jornada na INNER Studios! Bem-vindo à equipa!\n\nPara garantirmos que tens tudo o que precisas para a tua integração, pedimos que completes as seguintes tarefas de onboarding geral:\n\n**Tarefas de Onboarding Geral:**\n1. **Entrar no Discord:** Junta-te ao nosso servidor oficial de Discord através do link abaixo.\n2. **Entrar no Teams:** Junta-te à nossa comunidade no Microsoft Teams através do link abaixo.\n3. **Configurar o Horário de Trabalho:** No INNER Studios HR (para quem o deve configurar e não o HR).\n4. **Enviar uma Mensagem no Buzz:** Envia uma mensagem friendly de apresentação no Buzz do INNER Studios HR.\n5. **Marcar Reunião com o Manager:** Indica as tuas disponibilidades de horários no portal para agendarmos a tua primeira reunião de alinhamento com o teu manager (via link de marcação do supervisor).\n\nEstamos muito felizes por te ter a bordo!",
                    $candidate->getFirstName()
                ),
                'Iniciar Onboarding',
                $url,
                [
                    'Aceder ao Microsoft Teams' => self::TEAMS_URL,
                    'Aceder ao Discord Oficial' => self::DISCORD_URL,
                ]
            )
        );

        $this->getConnection()->executeStatement(
            'UPDATE ohrm_innerstudios_candidate_onboarding
             SET onboarding_sent_at = NOW(), updated_at = NOW()
             WHERE candidate_id = :candidateId',
            ['candidateId' => $candidate->getId()]
        );
    }

    public function sendPendingConfirmations(): int
    {
        $this->ensureSchema();
        $db = $this->getEntityManager();

        $stmt = $this->getConnection()->executeQuery(
            'SELECT cv.id 
             FROM ohrm_job_candidate_vacancy cv
             INNER JOIN ohrm_job_candidate c ON c.id = cv.candidate_id
             LEFT JOIN ohrm_innerstudios_recruitment_email_log el ON el.event_key = CONCAT(\'candidate:\', cv.candidate_id, \':application-confirmation\')
             WHERE el.id IS NULL'
        );

        $pending = $stmt->fetchAllAssociative();
        $sent = 0;

        $repo = $db->getRepository(CandidateVacancy::class);
        foreach ($pending as $row) {
            $candidateVacancy = $repo->find((int)$row['id']);
            if ($candidateVacancy) {
                $this->sendApplicationConfirmation($candidateVacancy);
                $sent++;
            }
        }

        return $sent;
    }

    public function getPortalContext(string $token, string $type, bool $allowUsed = false): ?array
    {
        $this->ensureSchema();
        $row = $this->getConnection()->fetchAssociative(
            'SELECT t.*, c.first_name, c.middle_name, c.last_name, c.email,
                    v.name AS vacancy_name, v.hiring_manager_id,
                    e.emp_firstname AS manager_first_name, e.emp_lastname AS manager_last_name
             FROM ohrm_innerstudios_recruitment_public_token t
             INNER JOIN ohrm_job_candidate c ON c.id = t.candidate_id
             LEFT JOIN ohrm_job_vacancy v ON v.id = t.vacancy_id
             LEFT JOIN hs_hr_employee e ON e.emp_number = v.hiring_manager_id
             WHERE t.token_hash = :tokenHash
               AND t.type = :type
               AND t.expires_at > NOW()
               ' . ($allowUsed ? '' : 'AND t.used_at IS NULL'),
            ['tokenHash' => $this->hashToken($token), 'type' => $type]
        );

        return $row ?: null;
    }

    public function completeDetails(string $token, array $data): bool
    {
        $context = $this->getPortalContext($token, self::TOKEN_TYPE_DETAILS);
        if (!$context) {
            return false;
        }

        $this->ensureOnboardingRow((int)$context['candidate_id'], (int)$context['vacancy_id']);
        $this->getConnection()->executeStatement(
            'UPDATE ohrm_innerstudios_candidate_onboarding
             SET full_name = :fullName,
                 address = :address,
                 citizen_card = :citizenCard,
                 nif = :nif,
                 details_completed_at = NOW(),
                 updated_at = NOW()
             WHERE candidate_id = :candidateId',
            [
                'candidateId' => (int)$context['candidate_id'],
                'fullName' => trim((string)$data['fullName']),
                'address' => trim((string)$data['address']),
                'citizenCard' => trim((string)$data['citizenCard']),
                'nif' => trim((string)$data['nif']),
            ]
        );

        $this->markTokenUsed($token, self::TOKEN_TYPE_DETAILS);
        $this->sendContractLink((int)$context['candidate_id']);
        return true;
    }

    public function uploadSignedContract(string $token, Base64Attachment $attachment): bool
    {
        $context = $this->getPortalContext($token, self::TOKEN_TYPE_CONTRACT);
        if (!$context) {
            return false;
        }

        $this->getConnection()->executeStatement(
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
                'candidateId' => (int)$context['candidate_id'],
                'fileName' => $attachment->getFilename(),
                'fileType' => $attachment->getFileType(),
                'fileSize' => $attachment->getSize(),
                'fileContent' => $attachment->getContent(),
            ]
        );
        $this->ensureOnboardingRow((int)$context['candidate_id'], (int)$context['vacancy_id']);
        $this->getConnection()->executeStatement(
            'UPDATE ohrm_innerstudios_candidate_onboarding
             SET contract_uploaded_at = NOW(), updated_at = NOW()
             WHERE candidate_id = :candidateId',
            ['candidateId' => (int)$context['candidate_id']]
        );

        $this->markTokenUsed($token, self::TOKEN_TYPE_CONTRACT);
        return true;
    }

    public function scheduleInterview(string $token, string $date, string $time): bool
    {
        $context = $this->getPortalContext($token, self::TOKEN_TYPE_SCHEDULE);
        if (!$context || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            return false;
        }

        $candidateVacancy = $this->getConnection()->fetchAssociative(
            'SELECT id FROM ohrm_job_candidate_vacancy WHERE candidate_id = :candidateId AND vacancy_id = :vacancyId LIMIT 1',
            ['candidateId' => (int)$context['candidate_id'], 'vacancyId' => (int)$context['vacancy_id']]
        );
        if (!$candidateVacancy) {
            return false;
        }

        $interviewId = $this->getConnection()->fetchOne(
            'SELECT id FROM ohrm_job_interview WHERE candidate_vacancy_id = :candidateVacancyId ORDER BY id DESC LIMIT 1',
            ['candidateVacancyId' => (int)$candidateVacancy['id']]
        );
        if ($interviewId) {
            $this->getConnection()->executeStatement(
                'UPDATE ohrm_job_interview
                 SET interview_name = :name, interview_date = :date, interview_time = :time, note = :note
                 WHERE id = :id',
                [
                    'id' => (int)$interviewId,
                    'name' => 'Entrevista INNER Studios',
                    'date' => $date,
                    'time' => $time . ':00',
                    'note' => 'Marcada pelo candidato através do portal público.',
                ]
            );
        } else {
            $this->getConnection()->executeStatement(
                'INSERT INTO ohrm_job_interview
                    (candidate_vacancy_id, candidate_id, interview_name, interview_date, interview_time, note)
                 VALUES (:candidateVacancyId, :candidateId, :name, :date, :time, :note)',
                [
                    'candidateVacancyId' => (int)$candidateVacancy['id'],
                    'candidateId' => (int)$context['candidate_id'],
                    'name' => 'Entrevista INNER Studios',
                    'date' => $date,
                    'time' => $time . ':00',
                    'note' => 'Marcada pelo candidato através do portal público.',
                ]
            );
            $interviewId = (int)$this->getConnection()->lastInsertId();
        }

        if (!empty($context['hiring_manager_id'])) {
            $this->getConnection()->executeStatement(
                'INSERT IGNORE INTO ohrm_job_interview_interviewer (interview_id, interviewer_id)
                 VALUES (:interviewId, :interviewerId)',
                ['interviewId' => (int)$interviewId, 'interviewerId' => (int)$context['hiring_manager_id']]
            );
        }

        $this->getConnection()->executeStatement(
            'UPDATE ohrm_job_candidate_vacancy SET status = :status WHERE id = :id',
            ['id' => (int)$candidateVacancy['id'], 'status' => CandidateService::STATUS_MAP[WorkflowStateMachine::RECRUITMENT_APPLICATION_ACTION_SHEDULE_INTERVIEW]]
        );
        $this->getConnection()->executeStatement(
            'INSERT INTO ohrm_job_candidate_history
                (candidate_id, vacancy_id, candidate_vacancy_name, interview_id, action, performed_by, performed_date, note)
             VALUES (:candidateId, :vacancyId, :vacancyName, :interviewId, :action, :performedBy, NOW(), :note)',
            [
                'candidateId' => (int)$context['candidate_id'],
                'vacancyId' => (int)$context['vacancy_id'],
                'vacancyName' => (string)$context['vacancy_name'],
                'interviewId' => (int)$interviewId,
                'action' => WorkflowStateMachine::RECRUITMENT_APPLICATION_ACTION_SHEDULE_INTERVIEW,
                'performedBy' => !empty($context['hiring_manager_id']) ? (int)$context['hiring_manager_id'] : null,
                'note' => 'Marcada pelo candidato através do portal público.',
            ]
        );

        $agendaResult = $this->getAgendaClient()->createBooking([
            'candidateId' => (int)$context['candidate_id'],
            'candidateEmail' => (string)$context['email'],
            'vacancyId' => (int)$context['vacancy_id'],
            'date' => $date,
            'time' => $time,
            'teamManagerEmpNumber' => !empty($context['hiring_manager_id']) ? (int)$context['hiring_manager_id'] : null,
        ]);
        $this->logAgendaResult((int)$context['candidate_id'], self::TOKEN_TYPE_SCHEDULE, $agendaResult);

        $this->markTokenUsed($token, self::TOKEN_TYPE_SCHEDULE);
        return true;
    }

    public function getContractTemplate(): ?array
    {
        $this->ensureSchema();
        $template = $this->getConnection()->fetchAssociative(
            'SELECT file_name, file_type, file_content
             FROM ohrm_innerstudios_recruitment_template
             ORDER BY updated_at DESC
             LIMIT 1'
        );

        return $template ?: null;
    }

    public function getAgendaAvailability(array $context): array
    {
        return $this->getAgendaClient()->getAvailability($context);
    }

    public function markOnboardingAvailability(string $token, string $availability): bool
    {
        $context = $this->getPortalContext($token, self::TOKEN_TYPE_ONBOARDING, true);
        if (!$context) {
            return false;
        }

        $this->ensureOnboardingRow((int)$context['candidate_id'], (int)$context['vacancy_id']);
        $this->getConnection()->executeStatement(
            'UPDATE ohrm_innerstudios_candidate_onboarding
             SET final_meeting_availability = :availability, updated_at = NOW()
             WHERE candidate_id = :candidateId',
            ['candidateId' => (int)$context['candidate_id'], 'availability' => trim($availability)]
        );

        return true;
    }

    private function sendOnce(string $eventKey, ?string $to, string $subject, string $body): void
    {
        $this->ensureSchema();
        if (empty($to) || $this->getConnection()->fetchOne(
            'SELECT id FROM ohrm_innerstudios_recruitment_email_log WHERE event_key = :eventKey LIMIT 1',
            ['eventKey' => $eventKey]
        )) {
            return;
        }

        $status = 'failed';
        $error = null;
        try {
            $emailService = $this->getEmailService();
            if (!$emailService->isConfigSet()) {
                $error = 'Email configuration is not set.';
            } else {
                $emailService->setMessageFrom([self::FROM_EMAIL => self::FROM_NAME]);
                $emailService->setMessageTo([$to]);
                $emailService->setMessageSubject($subject);
                $emailService->setMessageBody($body);
                $status = $emailService->sendEmail() ? 'sent' : 'failed';
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $this->getLogger()->error('Recruitment email failed: ' . $e->getMessage());
        }

        $this->getConnection()->executeStatement(
            'INSERT INTO ohrm_innerstudios_recruitment_email_log
                (event_key, recipient_email, subject, status, error_message, created_at)
             VALUES (:eventKey, :recipientEmail, :subject, :status, :errorMessage, NOW())',
            [
                'eventKey' => $eventKey,
                'recipientEmail' => $to,
                'subject' => $subject,
                'status' => $status,
                'errorMessage' => $error,
            ]
        );
    }

    private function createToken(string $type, int $candidateId, ?int $vacancyId, DateInterval $ttl): string
    {
        $this->ensureSchema();
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable())->add($ttl)->format('Y-m-d H:i:s');
        $this->ensureOnboardingRow($candidateId, $vacancyId);

        $this->getConnection()->executeStatement(
            'INSERT INTO ohrm_innerstudios_recruitment_public_token
                (token_hash, type, candidate_id, vacancy_id, expires_at, metadata, created_at)
             VALUES (:tokenHash, :type, :candidateId, :vacancyId, :expiresAt, :metadata, NOW())',
            [
                'tokenHash' => $this->hashToken($token),
                'type' => $type,
                'candidateId' => $candidateId,
                'vacancyId' => $vacancyId,
                'expiresAt' => $expiresAt,
                'metadata' => '{}',
            ]
        );

        return $token;
    }

    private function markTokenUsed(string $token, string $type): void
    {
        $this->getConnection()->executeStatement(
            'UPDATE ohrm_innerstudios_recruitment_public_token
             SET used_at = NOW()
             WHERE token_hash = :tokenHash AND type = :type',
            ['tokenHash' => $this->hashToken($token), 'type' => $type]
        );
    }

    private function ensureOnboardingRow(int $candidateId, ?int $vacancyId): void
    {
        $this->getConnection()->executeStatement(
            'INSERT INTO ohrm_innerstudios_candidate_onboarding
                (candidate_id, vacancy_id, created_at, updated_at)
             VALUES (:candidateId, :vacancyId, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                vacancy_id = COALESCE(VALUES(vacancy_id), vacancy_id),
                updated_at = NOW()',
            ['candidateId' => $candidateId, 'vacancyId' => $vacancyId]
        );
    }

    private function getCandidateContext(int $candidateId): ?array
    {
        $this->ensureSchema();
        $row = $this->getConnection()->fetchAssociative(
            'SELECT c.id AS candidate_id, c.first_name, c.middle_name, c.last_name, c.email,
                    cv.vacancy_id, v.name AS vacancy_name, v.hiring_manager_id
             FROM ohrm_job_candidate c
             LEFT JOIN ohrm_job_candidate_vacancy cv ON cv.candidate_id = c.id
             LEFT JOIN ohrm_job_vacancy v ON v.id = cv.vacancy_id
             WHERE c.id = :candidateId
             ORDER BY cv.id DESC
             LIMIT 1',
            ['candidateId' => $candidateId]
        );

        return $row ?: null;
    }

    private function hasContractTemplate(): bool
    {
        return (bool)$this->getConnection()->fetchOne(
            'SELECT id FROM ohrm_innerstudios_recruitment_template LIMIT 1'
        );
    }

    private function renderEmail(
        string $title,
        string $message,
        ?string $buttonLabel = null,
        ?string $buttonUrl = null,
        array $links = []
    ): string {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        $button = '';
        if ($buttonLabel && $buttonUrl) {
            $button = sprintf(
                '<div style="margin: 32px 0; text-align: center;">
                  <a href="%s" style="background-color: #28bda0; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 15px; display: inline-block; box-shadow: 0 4px 12px rgba(40, 189, 160, 0.15);">%s</a>
                </div>',
                htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8')
            );
        }

        $extraLinks = '';
        foreach ($links as $label => $url) {
            $extraLinks .= sprintf(
                '<li style="margin-bottom: 10px;"><a href="%s" style="color: #28bda0; text-decoration: none; font-weight: 600;">%s</a></li>',
                htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8')
            );
        }
        if ($extraLinks !== '') {
            $extraLinks = '<div style="margin-top: 30px; padding: 20px; background-color: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;"><h3 style="margin-top: 0; margin-bottom: 12px; color: #0f172a; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Acesso Rápido</h3><ul style="padding-left: 20px; margin: 0; color: #4b5563; font-size: 14px;">' . $extraLinks . '</ul></div>';
        }

        return <<<HTML
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #374151; margin: 0; padding: 40px 0; width: 100%;">
  <tr>
    <td align="center">
      <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
        <!-- Header -->
        <tr>
          <td style="background-color: #ffffff; padding: 32px 40px; text-align: center; border-bottom: 1px solid #f3f4f6;">
            <div style="font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: 2px; text-transform: uppercase;">
              INNER <span style="color: #28bda0;">STUDIOS</span>
            </div>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding: 40px 40px 30px 40px;">
            <h1 style="font-size: 22px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 24px; letter-spacing: -0.02em;">{$safeTitle}</h1>
            <div style="font-size: 15px; line-height: 1.6; color: #4b5563;">
              {$safeMessage}
            </div>
            {$button}
            {$extraLinks}
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="padding: 0 40px 40px 40px; text-align: center;">
            <hr style="border: 0; border-top: 1px solid #f3f4f6; margin-bottom: 24px;">
            <p style="font-size: 12px; color: #9ca3af; line-height: 1.5; margin: 0;">
              Este é um e-mail automático enviado pela INNER Studios. Por favor, não respondas diretamente a esta mensagem.<br>
              © 2026 INNER Studios. Todos os direitos reservados.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
HTML;
    }

    private function buildPublicUrl(string $type, string $token): string
    {
        return rtrim($this->getBaseUrl(), '/') . '/web/index.php/recruitment/public/' . $type . '/' . $token;
    }

    private function getBaseUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? null;
        if (!$host) {
            return self::FALLBACK_BASE_URL;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
        return $scheme . '://' . $host;
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function logAgendaResult(int $candidateId, string $context, array $result): void
    {
        $this->getConnection()->executeStatement(
            'INSERT INTO ohrm_innerstudios_recruitment_agenda_log
                (candidate_id, context, status, error_message, created_at)
             VALUES (:candidateId, :context, :status, :errorMessage, NOW())',
            [
                'candidateId' => $candidateId,
                'context' => $context,
                'status' => $result['ok'] ? 'ok' : 'failed',
                'errorMessage' => $result['error'] ?? null,
            ]
        );
    }

    private function tableExists(string $tableName): bool
    {
        try {
            $result = $this->getConnection()->fetchOne(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName LIMIT 1",
                ['tableName' => $tableName]
            );
            return (bool)$result;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function ensureSchema(): void
    {
        $connection = $this->getConnection();

        if (!$this->tableExists('ohrm_innerstudios_recruitment_public_token')) {
            $connection->executeStatement(
                'CREATE TABLE IF NOT EXISTS ohrm_innerstudios_recruitment_public_token (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    token_hash CHAR(64) NOT NULL UNIQUE,
                    type VARCHAR(32) NOT NULL,
                    candidate_id INT NOT NULL,
                    vacancy_id INT NULL,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    metadata LONGTEXT NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_innerstudios_token_lookup (type, candidate_id, vacancy_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        if (!$this->tableExists('ohrm_innerstudios_candidate_onboarding')) {
            $connection->executeStatement(
                'CREATE TABLE IF NOT EXISTS ohrm_innerstudios_candidate_onboarding (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    candidate_id INT NOT NULL UNIQUE,
                    vacancy_id INT NULL,
                    full_name VARCHAR(255) NULL,
                    address TEXT NULL,
                    citizen_card VARCHAR(100) NULL,
                    nif VARCHAR(50) NULL,
                    team_manager_emp_number INT NULL,
                    final_meeting_availability TEXT NULL,
                    details_completed_at DATETIME NULL,
                    contract_requested_at DATETIME NULL,
                    contract_uploaded_at DATETIME NULL,
                    onboarding_sent_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        if (!$this->tableExists('ohrm_innerstudios_recruitment_email_log')) {
            $connection->executeStatement(
                'CREATE TABLE IF NOT EXISTS ohrm_innerstudios_recruitment_email_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_key VARCHAR(190) NOT NULL UNIQUE,
                    recipient_email VARCHAR(255) NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    status VARCHAR(32) NOT NULL,
                    error_message TEXT NULL,
                    created_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        if (!$this->tableExists('ohrm_innerstudios_recruitment_agenda_log')) {
            $connection->executeStatement(
                'CREATE TABLE IF NOT EXISTS ohrm_innerstudios_recruitment_agenda_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    candidate_id INT NOT NULL,
                    context VARCHAR(64) NOT NULL,
                    status VARCHAR(32) NOT NULL,
                    error_message TEXT NULL,
                    created_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        if (!$this->tableExists('ohrm_innerstudios_recruitment_template')) {
            $connection->executeStatement(
                'CREATE TABLE IF NOT EXISTS ohrm_innerstudios_recruitment_template (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    file_name VARCHAR(255) NOT NULL,
                    file_type VARCHAR(100) NOT NULL,
                    file_size INT NOT NULL,
                    file_content LONGBLOB NOT NULL,
                    updated_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        if (!$this->tableExists('ohrm_innerstudios_hire_document')) {
            $connection->executeStatement(
                'CREATE TABLE IF NOT EXISTS ohrm_innerstudios_hire_document (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    candidate_id INT NOT NULL UNIQUE,
                    file_name VARCHAR(255) NOT NULL,
                    file_type VARCHAR(100) NOT NULL,
                    file_size INT NOT NULL,
                    file_content LONGBLOB NOT NULL,
                    uploaded_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        if (!$this->tableExists('ohrm_innerstudios_recruitment_offer')) {
            $connection->executeStatement(
                "CREATE TABLE IF NOT EXISTS ohrm_innerstudios_recruitment_offer (
                    candidate_id INT NOT NULL PRIMARY KEY,
                    work_shift_id INT NULL,
                    worker_decides TINYINT(1) NOT NULL DEFAULT 0,
                    offer_type VARCHAR(32) NOT NULL DEFAULT 'contratacao',
                    offer_letter_status VARCHAR(32) NULL,
                    offer_letter_submission_id INT NULL,
                    updated_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
            );
        } else {
            try {
                $connection->executeStatement(
                    "ALTER TABLE ohrm_innerstudios_recruitment_offer 
                     ADD COLUMN IF NOT EXISTS offer_type VARCHAR(32) NOT NULL DEFAULT 'contratacao',
                     ADD COLUMN IF NOT EXISTS offer_letter_status VARCHAR(32) NULL,
                     ADD COLUMN IF NOT EXISTS offer_letter_submission_id INT NULL"
                );
            } catch (\Exception $e) {
                // Ignore errors if columns already exist
            }
        }
    }

    private function getConnection()
    {
        return $this->getEntityManager()->getConnection();
    }

    private function getEmailService(): EmailService
    {
        if (!$this->emailService instanceof EmailService) {
            $this->emailService = new EmailService();
        }
        return $this->emailService;
    }

    private function getAgendaClient(): InnerStudiosAgendaClient
    {
        if (!$this->agendaClient instanceof InnerStudiosAgendaClient) {
            $this->agendaClient = new InnerStudiosAgendaClient();
        }
        return $this->agendaClient;
    }

    public static function getTeamsUrl(): string
    {
        return self::TEAMS_URL;
    }

    public static function getDiscordUrl(): string
    {
        return self::DISCORD_URL;
    }
}
