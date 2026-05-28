<?php

namespace OrangeHRM\Recruitment\Controller\PublicController;

use OrangeHRM\Core\Controller\AbstractController;
use OrangeHRM\Core\Controller\PublicControllerInterface;
use OrangeHRM\Core\Dto\Base64Attachment;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\Http\Response;
use OrangeHRM\Recruitment\Service\InnerStudiosRecruitmentPortalService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class InnerStudiosCandidatePortalController extends AbstractController implements PublicControllerInterface
{
    private ?InnerStudiosRecruitmentPortalService $portalService = null;

    public function handle(Request $request)
    {
        $flow = (string)$request->attributes->get('flow');
        $token = (string)$request->attributes->get('token');

        return match ($flow) {
            InnerStudiosRecruitmentPortalService::TOKEN_TYPE_SCHEDULE => $this->schedule($request, $token),
            InnerStudiosRecruitmentPortalService::TOKEN_TYPE_DETAILS => $this->details($request, $token),
            InnerStudiosRecruitmentPortalService::TOKEN_TYPE_CONTRACT => $this->contract($request, $token),
            InnerStudiosRecruitmentPortalService::TOKEN_TYPE_ONBOARDING => $this->onboarding($request, $token),
            'send-confirmations' => $this->sendConfirmations($request),
            default => $this->html('Link inválido', '<p>Este link não é válido.</p>', Response::HTTP_BAD_REQUEST),
        };
    }

    private function sendConfirmations(Request $request): Response
    {
        $secret = $request->query->get('secret');
        if ($secret !== 'innerstudios_recruitment_secret_confirmations_1293') {
            $response = $this->getResponse();
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent(json_encode(['error' => 'Forbidden']));
            return $response;
        }

        $sentCount = $this->getPortalService()->sendPendingConfirmations();
        
        $response = $this->getResponse();
        $response->setStatusCode(Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode(['status' => 'ok', 'sent' => $sentCount]));
        return $response;
    }

    private function schedule(Request $request, string $token): Response
    {
        $context = $this->getPortalService()->getPortalContext(
            $token,
            InnerStudiosRecruitmentPortalService::TOKEN_TYPE_SCHEDULE
        );
        if (!$context) {
            return $this->invalidLink();
        }

        if ($request->getMethod() === 'POST') {
            $ok = $this->getPortalService()->scheduleInterview(
                $token,
                (string)$request->request->get('date'),
                (string)$request->request->get('time')
            );
            return $this->html(
                $ok ? 'Entrevista marcada' : 'Não foi possível marcar',
                $ok
                    ? '<p>A tua entrevista ficou registada. Receberás novidades assim que a equipa confirmar internamente.</p>'
                    : '<p>Não foi possível guardar a marcação. Volta a tentar ou responde ao email de recrutamento.</p>',
                $ok ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
            );
        }

        $availability = $this->getPortalService()->getAgendaAvailability([
            'candidateId' => (int)$context['candidate_id'],
            'vacancyId' => (int)$context['vacancy_id'],
            'teamManagerEmpNumber' => !empty($context['hiring_manager_id']) ? (int)$context['hiring_manager_id'] : null,
        ]);

        $agendaMessage = $availability['ok'] ? '' : '<p class="notice">' . htmlspecialchars($availability['error'], ENT_QUOTES, 'UTF-8') . '</p>';
        $slotsJson = json_encode($availability['slots']);

        return $this->html(
            'Marca a tua entrevista',
            $agendaMessage . '
            <p>Escolhe um dia e horário para a entrevista da vaga <strong>' . htmlspecialchars((string)$context['vacancy_name'], ENT_QUOTES, 'UTF-8') . '</strong>.</p>
            
            <div id="calendar-widget" class="calendar-widget">
              <div class="calendar-header">
                <button type="button" class="calendar-nav-btn" id="cal-prev-btn">&larr;</button>
                <div class="calendar-month-title" id="cal-month-title"></div>
                <button type="button" class="calendar-nav-btn" id="cal-next-btn">&rarr;</button>
              </div>
              <div class="calendar-grid" id="cal-grid-container"></div>
            </div>
            
            <div class="time-container" style="display: none;">
              <h3>Horários disponíveis para <span id="selected-date-label"></span></h3>
              <div class="time-slots-grid" id="time-slots-container"></div>
            </div>

            <form method="post" class="form">
              <input type="hidden" name="date" required>
              <input type="hidden" name="time" required>
              <div class="submit-container" id="submit-wrapper" style="display: none;">
                <button type="submit" class="submit-btn">Confirmar marcação</button>
              </div>
            </form>
            
            <script>
              const allSlots = ' . $slotsJson . ';
              const slotsByDate = {};
              allSlots.forEach(slot => {
                if (!slotsByDate[slot.date]) {
                  slotsByDate[slot.date] = [];
                }
                slotsByDate[slot.date].push(slot.time);
              });

              // Extract unique months (format: YYYY-MM)
              const availableMonths = [];
              Object.keys(slotsByDate).sort().forEach(dateStr => {
                const monthStr = dateStr.substring(0, 7);
                if (!availableMonths.includes(monthStr)) {
                  availableMonths.push(monthStr);
                }
              });

              // Localized month and day names in PT
              const monthNamesPt = {
                "01": "Janeiro", "02": "Fevereiro", "03": "Março", "04": "Abril", "05": "Maio", "06": "Junho",
                "07": "Julho", "08": "Agosto", "09": "Setembro", "10": "Outubro", "11": "Novembro", "12": "Dezembro"
              };
              const weekdayHeaders = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"];

              let currentMonthIndex = 0;
              let selectedDateStr = "";

              // Function to render the calendar month
              function renderCalendar(monthIndex) {
                if (monthIndex < 0 || monthIndex >= availableMonths.length) return;
                currentMonthIndex = monthIndex;
                const activeMonthStr = availableMonths[currentMonthIndex];
                if (!activeMonthStr) return;

                const parts = activeMonthStr.split("-");
                const year = parts[0];
                const monthVal = parts[1];
                const monthNum = parseInt(monthVal, 10);
                const yearNum = parseInt(year, 10);

                // Update title
                const titleEl = document.getElementById("cal-month-title");
                titleEl.textContent = (monthNamesPt[monthVal] || activeMonthStr) + " " + year;

                // Update nav buttons visibility
                document.getElementById("cal-prev-btn").style.opacity = currentMonthIndex > 0 ? "1" : "0.3";
                document.getElementById("cal-prev-btn").style.pointerEvents = currentMonthIndex > 0 ? "auto" : "none";
                document.getElementById("cal-next-btn").style.opacity = currentMonthIndex < availableMonths.length - 1 ? "1" : "0.3";
                document.getElementById("cal-next-btn").style.pointerEvents = currentMonthIndex < availableMonths.length - 1 ? "auto" : "none";

                const grid = document.getElementById("cal-grid-container");
                grid.innerHTML = "";

                // Render weekdays headers
                weekdayHeaders.forEach(day => {
                  const header = document.createElement("div");
                  header.className = "calendar-day-header";
                  header.textContent = day;
                  grid.appendChild(header);
                });

                // Get first day of month and number of days
                const firstDayDate = new Date(yearNum, monthNum - 1, 1);
                let startDay = firstDayDate.getDay();
                // Map to Mon = 0 ... Sun = 6
                startDay = startDay === 0 ? 6 : startDay - 1;

                const numDays = new Date(yearNum, monthNum, 0).getDate();

                // Render empty padding cells
                for (let i = 0; i < startDay; i++) {
                  const cell = document.createElement("div");
                  cell.className = "calendar-day-cell empty";
                  grid.appendChild(cell);
                }

                // Render days cells
                for (let d = 1; d <= numDays; d++) {
                  const cell = document.createElement("div");
                  cell.className = "calendar-day-cell";
                  cell.textContent = d;

                  const dateStr = year + "-" + String(monthNum).padStart(2, "0") + "-" + String(d).padStart(2, "0");

                  if (slotsByDate[dateStr]) {
                    cell.classList.add("available");
                    if (dateStr === selectedDateStr) {
                      cell.classList.add("active");
                    }
                    cell.addEventListener("click", () => {
                      document.querySelectorAll(".calendar-day-cell.available").forEach(c => c.classList.remove("active"));
                      cell.classList.add("active");
                      selectDate(dateStr);
                    });
                  } else {
                    cell.classList.add("disabled");
                  }
                  grid.appendChild(cell);
                }
              }

              function selectDate(dateStr) {
                selectedDateStr = dateStr;
                document.querySelector("[name=date]").value = dateStr;
                document.querySelector("[name=time]").value = "";
                document.getElementById("submit-wrapper").style.display = "none";

                const parts = dateStr.split("-");
                document.getElementById("selected-date-label").textContent = parts[2] + "/" + parts[1] + "/" + parts[0];

                const container = document.getElementById("time-slots-container");
                container.innerHTML = "";
                const times = slotsByDate[dateStr] || [];

                times.forEach(time => {
                  const btn = document.createElement("button");
                  btn.type = "button";
                  btn.className = "time-slot-btn";
                  btn.textContent = time;
                  btn.addEventListener("click", () => {
                    document.querySelectorAll(".time-slot-btn").forEach(b => b.classList.remove("active"));
                    btn.classList.add("active");
                    document.querySelector("[name=time]").value = time;
                    document.getElementById("submit-wrapper").style.display = "block";
                  });
                  container.appendChild(btn);
                });

                document.querySelector(".time-container").style.display = "block";
                
                setTimeout(() => {
                  document.querySelector(".time-container").scrollIntoView({ behavior: "smooth", block: "nearest" });
                }, 100);
              }

              // Event listeners for month navigation
              document.getElementById("cal-prev-btn").addEventListener("click", () => {
                if (currentMonthIndex > 0) {
                  renderCalendar(currentMonthIndex - 1);
                }
              });

              document.getElementById("cal-next-btn").addEventListener("click", () => {
                if (currentMonthIndex < availableMonths.length - 1) {
                  renderCalendar(currentMonthIndex + 1);
                }
              });

              // Initial calendar rendering
              if (availableMonths.length > 0) {
                renderCalendar(0);
              } else {
                document.getElementById("calendar-widget").innerHTML = \'<p class="notice">Sem datas disponíveis no momento.</p>\';
              }
            </script>'
        );
    }

    private function details(Request $request, string $token): Response
    {
        $context = $this->getPortalService()->getPortalContext(
            $token,
            InnerStudiosRecruitmentPortalService::TOKEN_TYPE_DETAILS
        );
        if (!$context) {
            return $this->invalidLink();
        }

        if ($request->getMethod() === 'POST') {
            $required = ['fullName', 'address', 'citizenCard', 'nif'];
            foreach ($required as $key) {
                if (trim((string)$request->request->get($key)) === '') {
                    return $this->html('Dados em falta', '<p>Preenche todos os campos obrigatórios.</p>', Response::HTTP_BAD_REQUEST);
                }
            }

            $ok = $this->getPortalService()->completeDetails($token, $request->request->all());
            return $this->html(
                $ok ? 'Dados recebidos' : 'Link inválido',
                $ok
                    ? '<p>Obrigado. Guardámos os teus dados e vamos avançar para a fase do contrato.</p>'
                    : '<p>Não foi possível guardar os dados.</p>',
                $ok ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
            );
        }

        $defaultName = trim(
            (string)$context['first_name'] . ' ' .
            (string)($context['middle_name'] ?? '') . ' ' .
            (string)$context['last_name']
        );

        return $this->html(
            'Dados para onboarding',
            '<p>Confirma os dados necessários para prepararmos o contrato.</p>
            <form method="post" class="form">
              <label>Nome completo<input required name="fullName" value="' . htmlspecialchars($defaultName, ENT_QUOTES, 'UTF-8') . '"></label>
              <label>Morada<textarea required name="address" rows="4"></textarea></label>
              <label>Cartão de Cidadão<input required name="citizenCard"></label>
              <label>NIF<input required name="nif"></label>
              <button type="submit">Enviar dados</button>
            </form>'
        );
    }

    private function contract(Request $request, string $token): Response
    {
        $context = $this->getPortalService()->getPortalContext(
            $token,
            InnerStudiosRecruitmentPortalService::TOKEN_TYPE_CONTRACT
        );
        if (!$context) {
            return $this->invalidLink();
        }

        if ($request->query->get('download') === '1') {
            $template = $this->getPortalService()->getContractTemplate();
            if (!$template) {
                return $this->html('Contrato indisponível', '<p>O contrato ainda não está disponível.</p>', Response::HTTP_NOT_FOUND);
            }

            $response = $this->getResponse();
            $response->setContent($template['file_content']);
            $response->headers->set('Content-Type', $template['file_type'] ?: 'application/octet-stream');
            $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', addslashes($template['file_name'])));
            return $response;
        }

        if ($request->getMethod() === 'POST') {
            $file = $request->files->get('signedContract');
            if (!$file instanceof UploadedFile) {
                return $this->html('Ficheiro em falta', '<p>Escolhe o contrato assinado para upload.</p>', Response::HTTP_BAD_REQUEST);
            }
            $attachment = Base64Attachment::createFromUploadedFile($file);
            $ok = $this->getPortalService()->uploadSignedContract($token, $attachment);
            return $this->html(
                $ok ? 'Contrato recebido' : 'Não foi possível guardar',
                $ok
                    ? '<p>Recebemos o contrato assinado. A equipa de HR vai validar o documento.</p>'
                    : '<p>Não foi possível guardar o contrato assinado.</p>',
                $ok ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
            );
        }

        return $this->html(
            'Contrato para assinatura',
            '<p>Descarrega o contrato, assina-o e envia aqui a versão assinada.</p>
            <p><a class="button secondary" href="?download=1">Descarregar contrato</a></p>
            <form method="post" enctype="multipart/form-data" class="form">
              <label>Contrato assinado<input required name="signedContract" type="file" accept="application/pdf,image/png,image/jpeg"></label>
              <button type="submit">Enviar contrato assinado</button>
            </form>'
        );
    }

    private function onboarding(Request $request, string $token): Response
    {
        $context = $this->getPortalService()->getPortalContext(
            $token,
            InnerStudiosRecruitmentPortalService::TOKEN_TYPE_ONBOARDING,
            true
        );
        if (!$context) {
            return $this->invalidLink();
        }

        if ($request->getMethod() === 'POST') {
            $this->getPortalService()->markOnboardingAvailability(
                $token,
                (string)$request->request->get('availability')
            );
            return $this->html('Disponibilidade recebida', '<p>Obrigado. O teu team manager vai usar esta disponibilidade para combinar a reunião.</p>');
        }

        $managerName = trim((string)($context['manager_first_name'] ?? '') . ' ' . (string)($context['manager_last_name'] ?? ''));
        if ($managerName === '') {
            $managerName = 'team manager';
        }

        return $this->html(
            'Onboarding INNER Studios HR',
            '<p>Bem-vindo. Junta-te às comunidades e indica disponibilidade para reunir com ' . htmlspecialchars($managerName, ENT_QUOTES, 'UTF-8') . '.</p>
            <p><a class="button secondary" href="' . InnerStudiosRecruitmentPortalService::getTeamsUrl() . '">Entrar no Teams</a></p>
            <p><a class="button secondary" href="' . InnerStudiosRecruitmentPortalService::getDiscordUrl() . '">Entrar no Discord</a></p>
            <form method="post" class="form">
              <label>Disponibilidade para reunião<textarea required name="availability" rows="5" placeholder="Ex: segunda ou quarta depois das 18h"></textarea></label>
              <button type="submit">Enviar disponibilidade</button>
            </form>'
        );
    }

    private function invalidLink(): Response
    {
        return $this->html(
            'Link expirado ou inválido',
            '<p>Este link já expirou, já foi utilizado ou não existe. Responde ao email de recrutamento para receber ajuda.</p>',
            Response::HTTP_NOT_FOUND
        );
    }

    private function html(string $title, string $body, int $status = Response::HTTP_OK): Response
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $content = <<<HTML
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$safeTitle} | INNER Studios Recruitment</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-color: #0b0f19;
      --card-bg: rgba(22, 30, 49, 0.7);
      --card-border: rgba(40, 189, 160, 0.15);
      --primary: #28bda0;
      --primary-hover: #1f9e85;
      --text-main: #f3f4f6;
      --text-muted: #9ca3af;
      --input-bg: #111827;
      --input-border: #1f2937;
      --shadow-glow: rgba(40, 189, 160, 0.1);
    }
    
    * { box-sizing: border-box; }
    body {
      margin: 0;
      background-color: var(--bg-color);
      background-image: 
        radial-gradient(at 0% 0%, rgba(40, 189, 160, 0.08) 0px, transparent 50%),
        radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.05) 0px, transparent 50%);
      color: var(--text-main);
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    
    main {
      max-width: 640px;
      width: 100%;
      margin: 0 auto;
      padding: 40px 20px;
    }
    
    .logo-container {
      text-align: center;
      margin-bottom: 32px;
    }
    
    .logo-text {
      font-family: 'Outfit', sans-serif;
      font-weight: 800;
      font-size: 28px;
      letter-spacing: -0.03em;
      text-transform: uppercase;
      background: linear-gradient(135deg, #ffffff 30%, var(--primary) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    
    .panel {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-radius: 16px;
      padding: 40px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 0 50px var(--shadow-glow);
    }
    
    h1 {
      font-family: 'Outfit', sans-serif;
      font-size: 32px;
      font-weight: 700;
      margin: 0 0 16px;
      color: #ffffff;
      letter-spacing: -0.02em;
    }
    
    p {
      line-height: 1.6;
      color: var(--text-muted);
      font-size: 15px;
      margin-bottom: 24px;
    }
    
    .form {
      display: grid;
      gap: 20px;
      margin-top: 28px;
    }
    
    label {
      display: grid;
      gap: 8px;
      font-weight: 500;
      color: var(--text-main);
      font-size: 14px;
    }
    
    input, textarea {
      background-color: var(--input-bg);
      border: 1px solid var(--input-border);
      border-radius: 8px;
      padding: 14px;
      color: #ffffff;
      font-family: inherit;
      font-size: 15px;
      transition: all 0.2s ease;
      width: 100%;
    }
    
    input:focus, textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(40, 189, 160, 0.15);
    }
    
    button, .button {
      border: 0;
      border-radius: 8px;
      background: var(--primary);
      color: #0b0f19;
      padding: 15px 24px;
      font-weight: 600;
      font-size: 15px;
      text-decoration: none;
      display: inline-block;
      cursor: pointer;
      text-align: center;
      transition: all 0.2s ease;
    }
    
    button:hover, .button:hover {
      background: var(--primary-hover);
      transform: translateY(-1px);
    }
    
    button:active, .button:active {
      transform: translateY(0);
    }
    
    .secondary {
      background: #1f2937;
      color: #ffffff;
      border: 1px solid #374151;
    }
    
    .secondary:hover {
      background: #374151;
    }
    
    .notice {
      background: rgba(254, 110, 0, 0.1);
      border: 1px solid rgba(254, 110, 0, 0.2);
      border-radius: 8px;
      padding: 14px 18px;
      color: #fdba74;
      font-size: 14px;
      margin-bottom: 24px;
      line-height: 1.5;
    }
    
    /* Calendar Widget styles */
    .calendar-widget {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 16px;
      padding: 24px;
      margin: 24px 0;
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.05);
    }
    
    .calendar-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding: 0 4px;
    }
    
    .calendar-month-title {
      font-family: 'Outfit', sans-serif;
      font-size: 18px;
      font-weight: 700;
      color: #ffffff;
      letter-spacing: -0.01em;
    }
    
    .calendar-nav-btn {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #ffffff;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 16px;
      padding: 0;
      transition: all 0.2s ease;
      line-height: 1;
    }
    
    .calendar-nav-btn:hover {
      background: var(--primary);
      color: #0b0f19;
      border-color: var(--primary);
      transform: scale(1.05);
    }
    
    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 8px;
      text-align: center;
    }
    
    .calendar-day-header {
      font-size: 12px;
      font-weight: 600;
      color: var(--text-muted);
      text-transform: uppercase;
      padding-bottom: 8px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      letter-spacing: 0.05em;
    }
    
    .calendar-day-cell {
      aspect-ratio: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      user-select: none;
      border: 1px solid transparent;
    }
    
    .calendar-day-cell.empty {
      visibility: hidden;
    }
    
    .calendar-day-cell.disabled {
      color: rgba(255, 255, 255, 0.12);
      pointer-events: none;
    }
    
    .calendar-day-cell.available {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.06);
      color: #ffffff;
      cursor: pointer;
    }
    
    .calendar-day-cell.available:hover {
      background: rgba(40, 189, 160, 0.08);
      border-color: rgba(40, 189, 160, 0.3);
      transform: scale(1.08);
    }
    
    .calendar-day-cell.available.active {
      background: var(--primary);
      color: #0b0f19;
      border-color: var(--primary);
      font-weight: 700;
      box-shadow: 0 0 16px rgba(40, 189, 160, 0.35);
      transform: scale(1.08);
    }

    .time-container {
      margin-top: 32px;
      padding-top: 24px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      animation: fadeIn 0.3s ease;
    }
    
    .time-container h3 {
      font-family: 'Outfit', sans-serif;
      font-size: 18px;
      font-weight: 600;
      color: #ffffff;
      margin: 0 0 16px;
    }
    
    .time-slots-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
      gap: 10px;
    }
    
    .time-slot-btn {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 8px;
      padding: 12px;
      color: var(--text-main);
      font-weight: 500;
      font-size: 14px;
      cursor: pointer;
      transition: all 0.2s ease;
      text-align: center;
    }
    
    .time-slot-btn:hover {
      background: rgba(40, 189, 160, 0.06);
      border-color: rgba(40, 189, 160, 0.3);
      transform: translateY(-1px);
    }
    
    .time-slot-btn.active {
      background: var(--primary);
      color: #0b0f19;
      border-color: var(--primary);
      font-weight: 600;
      box-shadow: 0 0 12px rgba(40, 189, 160, 0.25);
    }
    
    .submit-container {
      margin-top: 28px;
      display: flex;
      justify-content: flex-end;
      animation: fadeIn 0.3s ease;
    }
    
    .submit-btn {
      width: auto;
      padding: 14px 32px;
      font-size: 15px;
      border-radius: 8px;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(6px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    ul {
      padding-left: 20px;
      margin-bottom: 24px;
    }
    
    li {
      margin-bottom: 8px;
      color: var(--text-muted);
    }
    
    a {
      color: var(--primary);
      text-decoration: none;
      transition: color 0.2s ease;
    }
    
    a:hover {
      color: var(--primary-hover);
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <main>
    <div class="logo-container">
      <span class="logo-text">INNER Studios</span>
    </div>
    <section class="panel">
      <h1>{$safeTitle}</h1>
      {$body}
    </section>
  </main>
</body>
</html>
HTML;
        $response = $this->getResponse();
        $response->setStatusCode($status);
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        $response->setContent($content);
        return $response;
    }

    private function getPortalService(): InnerStudiosRecruitmentPortalService
    {
        if (!$this->portalService instanceof InnerStudiosRecruitmentPortalService) {
            $this->portalService = new InnerStudiosRecruitmentPortalService();
        }
        return $this->portalService;
    }
}
