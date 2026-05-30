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

namespace OrangeHRM\I18N\Controller;

use OrangeHRM\Config\Config;
use OrangeHRM\Core\Controller\AbstractFileController;
use OrangeHRM\Core\Controller\PublicControllerInterface;
use OrangeHRM\Core\Traits\Service\ConfigServiceTrait;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\Http\Response;
use OrangeHRM\Framework\Services;
use OrangeHRM\I18N\Service\I18NService;
use InvalidArgumentException;

class I18NMessagesController extends AbstractFileController implements PublicControllerInterface
{
    use ConfigServiceTrait;

    /**
     * @return I18NService
     */
    public function getI18NService(): I18NService
    {
        return $this->getContainer()->get(Services::I18N_SERVICE);
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $requestedLocale = $request->query->has('locale') ? $request->query->get('locale') : null;
        $locale = $this->normalizeLocale($requestedLocale);
        if (!is_string($locale) || trim($locale) === '') {
            $locale = $this->getConfigService()->getAdminLocalizationDefaultLanguage();
        }

        try {
            $json = $this->getI18NService()->getTranslationMessagesAsJsonString($locale);
        } catch (InvalidArgumentException $e) {
            $fallbackLocale = $this->getConfigService()->getAdminLocalizationDefaultLanguage();
            $json = $this->getI18NService()->getTranslationMessagesAsJsonString($fallbackLocale);
        }

        if ($locale === 'pt_PT') {
            $data = json_decode($json, true);
            if (is_array($data)) {
                $ptDict = [
                    'Dashboard' => 'Painel de Controlo',
                    'PIM' => 'Gestão de Funcionários',
                    'Leave' => 'Férias e Licenças',
                    'Time' => 'Registo de Tempo',
                    'My Info' => 'A Minha Informação',
                    'Performance' => 'Desempenho',
                    'Admin' => 'Administração',
                    'Directory' => 'Diretório',
                    'Maintenance' => 'Manutenção',
                    'Buzz' => 'Mural (Buzz)',
                    'Recruitment' => 'Recrutamento',
                    'Claim' => 'Despesas',
                    'Time Sheets' => 'Folhas de Horas',
                    'Attendance' => 'Assiduidade',
                    'Search' => 'Procurar',
                    'Reset' => 'Limpar',
                    'Add' => 'Adicionar',
                    'Delete' => 'Eliminar',
                    'Edit' => 'Editar',
                    'Save' => 'Guardar',
                    'Cancel' => 'Cancelar',
                    'Username' => 'Nome de Utilizador',
                    'Password' => 'Palavra-passe',
                    'Confirm Password' => 'Confirmar Palavra-passe',
                    'Email' => 'E-mail',
                    'Work Email' => 'E-mail Profissional',
                    'Other Email' => 'Outro E-mail',
                    'First Name' => 'Primeiro Nome',
                    'Last Name' => 'Último Nome',
                    'Middle Name' => 'Segundo Nome',
                    'Employee Id' => 'ID do Funcionário',
                    'Status' => 'Estado',
                    'Enabled' => 'Ativo',
                    'Disabled' => 'Inativo',
                    'Required' => 'Obrigatório',
                    'Invalid' => 'Inválido',
                    'Success' => 'Sucesso',
                    'Error' => 'Erro',
                    'Actions' => 'Ações',
                    'Select' => 'Selecionar',
                    'Confirm' => 'Confirmar',
                    'Login' => 'Iniciar Sessão',
                    'Logout' => 'Terminar Sessão',
                    'Profile Picture' => 'Foto de Perfil',
                    'Nationalities' => 'Nacionalidades',
                    'Nationalty' => 'Nacionalidade',
                    'Marital Status' => 'Estado Civil',
                    'Gender' => 'Género',
                    'Male' => 'Masculino',
                    'Female' => 'Feminino',
                    'Date of Birth' => 'Data de Nascimento',
                    'Military Service' => 'Serviço Militar',
                    'Smoker' => 'Fumador',
                    'License Number' => 'Número de Carta de Condução',
                    'License Expiry Date' => 'Data de Expiração da Carta',
                    'Employee Name' => 'Nome do Funcionário',
                    'Job Title' => 'Cargo',
                    'Sub Unit' => 'Subunidade',
                    'Employment Status' => 'Vínculo Laboral',
                    'System Users' => 'Utilizadores do Sistema',
                    'User Role' => 'Função de Utilizador',
                    'User Name' => 'Nome de Utilizador',
                    'Successfully Saved' => 'Guardado com sucesso',
                    'Successfully Updated' => 'Atualizado com sucesso',
                    'Successfully Deleted' => 'Eliminado com sucesso',
                    'Confirm Delete' => 'Confirmar Eliminação',
                    'Yes, Delete' => 'Sim, Eliminar',
                    'No, Keep' => 'Não, Manter',
                    'Records Found' => 'registos encontrados',
                    'No Records Found' => 'Nenhum registo encontrado',
                    'Vacancies' => 'Vagas',
                    'Candidates' => 'Candidatos',
                    'Job Titles' => 'Cargos',
                    'Pay Grades' => 'Escalões Salariais',
                    'Job Categories' => 'Categorias de Cargo',
                    'Work Shifts' => 'Turnos de Trabalho',
                    'General Information' => 'Informação Geral',
                    'Locations' => 'Localizações',
                    'Structure' => 'Estrutura',
                    'Skills' => 'Competências',
                    'Qualifications' => 'Qualificações',
                    'Education' => 'Educação',
                    'Licenses' => 'Licenças',
                    'Languages' => 'Idiomas',
                    'Memberships' => 'Associações',
                    'Configuration' => 'Configuração',
                    'Email Configuration' => 'Configuração de E-mail',
                    'Email Subscriptions' => 'Subscrições de E-mail',
                    'Localization' => 'Localização',
                    'Language Packages' => 'Pacotes de Idioma',
                    'Modules' => 'Módulos',
                    'Social Media Authenticator' => 'Autenticador de Redes Sociais',
                    'Register OAuth Client' => 'Registar Cliente OAuth',
                    'LDAP Configuration' => 'Configuração LDAP',
                    'Personal Details' => 'Dados Pessoais',
                    'Contact Details' => 'Dados de Contacto',
                    'Emergency Contacts' => 'Contactos de Emergência',
                    'Dependents' => 'Dependentes',
                    'Immigration' => 'Imigração',
                    'Job' => 'Trabalho',
                    'Salary' => 'Salário',
                    'Tax Exemptions' => 'Isenções Fiscais',
                    'Report-to' => 'Reporta a',
                    'Direct' => 'Direto',
                    'Indirect' => 'Indireto',
                    'Attachments' => 'Anexos',
                    'Punch In' => 'Entrar (Picagem)',
                    'Punch Out' => 'Sair (Picagem)',
                    'My Timesheet' => 'A Minha Folha de Horas',
                    'Employee Timesheets' => 'Folhas de Horas de Funcionários',
                    'Reports' => 'Relatórios',
                    'Project Info' => 'Informações do Projeto',
                    'Customers' => 'Clientes',
                    'Projects' => 'Projetos',
                    'My Records' => 'Os Meus Registos',
                    'Punch In/Out' => 'Registar Entrada/Saída',
                    'Employee Records' => 'Registos de Funcionários',
                    'Key Performance Indicators' => 'Indicadores de Desempenho (KPIs)',
                    'Trackers' => 'Seguimentos',
                    'Manage Reviews' => 'Gerir Avaliações',
                    'My Reviews' => 'As Minhas Avaliações',
                    'Review List' => 'Lista de Avaliações',
                    'Shortlist' => 'Selecionar (Shortlist)',
                    'Hire' => 'Contratar',
                    'Reject' => 'Rejeitar',
                    'Offer Job' => 'Oferecer Emprego',
                    'Schedule Interview' => 'Agendar Entrevista',
                    'Mark Interview Passed' => 'Aprovar na Entrevista',
                    'Mark Interview Failed' => 'Reprovar na Entrevista',
                    'Vacancy' => 'Vaga',
                    'Candidate' => 'Candidato',
                    'Hiring Manager' => 'Gestor de Contratação',
                    'Date of Application' => 'Data da Candidatura',
                    'Resume' => 'Currículo',
                    'Keywords' => 'Palavras-chave',
                    'Comment' => 'Comentário',
                    'Method of Application' => 'Método de Candidatura',
                    'Manual' => 'Manual',
                    'Online' => 'Online',
                    'Active' => 'Ativo',
                    'Archived' => 'Arquivado',
                    'Pending' => 'Pendente',
                    'Approved' => 'Aprovado',
                    'Rejected' => 'Rejeitado',
                    'Cancelled' => 'Cancelado',
                    'Assign Leave' => 'Atribuir Férias',
                    'Leave List' => 'Lista de Férias',
                    'My Leave' => 'As Minhas Férias',
                    'Apply' => 'Candidatar-se / Solicitar',
                    'Entitlements' => 'Direitos de Férias',
                    'Add Entitlements' => 'Adicionar Direitos de Férias',
                    'Employee Entitlements' => 'Direitos de Funcionários',
                    'My Entitlements' => 'Os Meus Direitos',
                    'Leave Entitlements and Usage Report' => 'Relatório de Direitos e Uso de Férias',
                    'My Leave Entitlements and Usage Report' => 'O Meu Relatório de Direitos e Uso',
                    'Leave Period' => 'Período de Férias',
                    'Leave Type' => 'Tipo de Férias',
                    'Days' => 'Dias',
                    'Hours' => 'Horas',
                    'Duration' => 'Duração',
                    'Description' => 'Descrição',
                    'Create' => 'Criar',
                    'Update' => 'Atualizar',
                    'View' => 'Ver',
                    'Details' => 'Detalhes',
                    'Type' => 'Tipo',
                    'Title' => 'Título',
                    'Name' => 'Nome',
                    'Key' => 'Chave',
                    'Value' => 'Valor',
                    'Options' => 'Opções',
                    'Settings' => 'Definições',
                    'Notifications' => 'Notificações',
                    'Awards' => 'Prémios',
                    'Staff Awards' => 'Prémios da Equipa',
                    'InnerFX' => 'InnerFX',
                    'Inner Circle' => 'Inner Circle',
                    'Confirm Access' => 'Confirmar Acesso',
                    'Confirmar Acesso' => 'Confirmar Acesso',
                    'Estás a dar acesso ao' => 'Estás a dar acesso ao',
                    'Confirmar' => 'Confirmar'
                ];

                $ptKeysDict = [
                    'dashboard.time_at_work' => 'Tempo de Trabalho',
                    'attendance.punched_in' => 'Entrada Registada',
                    'attendance.punched_out' => 'Saída Registada',
                    'attendance.not_punched_in' => 'Sem Entrada Registada',
                    'dashboard.state_today_at_time_timezone_offset' => '{lastState}: hoje às {time} (GMT {timezoneOffset})',
                    'general.today' => 'Hoje',
                    'dashboard.this_week' => 'Esta Semana',
                    'dashboard.my_actions' => 'As Minhas Ações',
                    'dashboard.n_pending_candidate_interview' => '{pendingActionsCount,plural, =0{Nenhuma entrevista agendada} one{(1) Candidato para Entrevista} other{(#) Candidatos para Entrevista}}',
                    'dashboard.quick_launch' => 'Acesso Rápido',
                    'general.timesheets' => 'Folhas de Horas',
                    'time.my_timesheet' => 'A Minha Folha de Horas',
                    'dashboard.buzz_latest_posts' => 'Últimas Publicações (Buzz)',
                    'dashboard.employees_on_leave_today' => 'Funcionários Ausentes/Em Férias Hoje',
                    'dashboard.leave_period_not_defined' => 'Período de Férias Não Definido',
                    'dashboard.employee_distribution_by_sub_unit' => 'Distribuição de Funcionários por Subunidade',
                    'dashboard.unassigned' => 'Não Atribuído',
                    'dashboard.employee_distribution_by_location' => 'Distribuição de Funcionários por Localização',
                    'buzz.most_recent_posts' => 'Publicações Mais Recentes',
                    'buzz.most_liked_posts' => 'Publicações Mais Gostadas',
                    'buzz.most_commented_posts' => 'Publicações Mais Comentadas',
                    'buzz.buzz_newsfeed' => 'Feed do Buzz',
                    'buzz.post_placeholder' => 'Partilhe algo com a sua equipa...',
                    'buzz.post' => 'Publicar',
                    'buzz.share_photos' => 'Partilhar Fotos',
                    'buzz.share_video' => 'Partilhar Vídeo',
                    'buzz.n_like' => '{likesCount,plural, =0{0 Gostos} one{1 Gosto} other{# Gostos}}',
                    'buzz.n_comment' => '{commentCount,plural, =0{0 Comentários} one{1 Comentário} other{# Comentários}}',
                    'buzz.n_share' => '{shareCount,plural, =0{0 Partilhas} one{1 Partilha} other{# Partilhas}}',
                    'buzz.upcoming_anniversaries' => 'Próximos Aniversários de Trabalho',
                    'general.no_records_found' => 'Nenhum registo encontrado',
                    'general.directory' => 'Diretório',
                    'general.n_records_found' => '{count,plural, =0{Nenhum registo encontrado} one{(1) Registo Encontrado} other{(#) Registos Encontrados}}',
                    'performance.employee_reviews' => 'Avaliações de Desempenho de Funcionários',
                    'pim.include' => 'Incluir',
                    'performance.review_status' => 'Estado da Avaliação',
                    'performance.review_period' => 'Período de Avaliação',
                    'performance.due_date' => 'Data Limite',
                    'general.candidates' => 'Candidatos',
                    'general.vacancies' => 'Vagas',
                    'recruitment.vacancy' => 'Vaga',
                    'recruitment.hiring_manager' => 'Gestor de Contratação',
                    'recruitment.candidate_name' => 'Nome do Candidato',
                    'recruitment.keywords' => 'Palavras-chave',
                    'recruitment.enter_comma_seperated_words...' => 'Introduza palavras separadas por vírgula...',
                    'recruitment.date_of_application' => 'Data da Candidatura',
                    'recruitment.method_of_application' => 'Método de Candidatura',
                    'recruitment.candidate' => 'Candidato',
                    'recruitment.interview_scheduled' => 'Entrevista Agendada',
                    'recruitment.job_offered' => 'Oferta de Emprego Realizada',
                    'recruitment.hired' => 'Contratado',
                    'recruitment.offer_declined' => 'Oferta Recusada',

                    // New PIM Keys
                    'pim.employee_information' => 'Informação do Funcionário',
                    'pim.supervisor_name' => 'Nome do Supervisor',
                    'pim.first_middle_name' => 'Nomes Próprios',
                    'pim.supervisor' => 'Supervisor',
                    'general.last_name' => 'Apelido',
                    'general.first_name' => 'Primeiro Nome',
                    'general.middle_name' => 'Segundo Nome',
                    'pim.middle_name' => 'Segundo Nome',
                    'pim.last_name' => 'Apelido',

                    // New Leave Keys
                    'leave.leave_period' => 'Período de Férias',
                    'leave.start_month' => 'Mês de Início',

                    // New Time Keys
                    'time.select_employee' => 'Selecionar Funcionário',
                    'time.timesheets_pending_action' => 'Folhas de Horas Pendentes de Ação',
                    'time.timesheet_period' => 'Período da Folha de Horas',

                    // New Dashboard Pending Action Plurals & Helpers
                    'dashboard.no_pending_actions' => 'Não existem ações pendentes',
                    'dashboard.n_pending_leave_request' => '{pendingActionsCount,plural, =0{Nenhum pedido de férias pendente} one{(1) Pedido de Férias para Aprovar} other{(#) Pedidos de Férias para Aprovar}}',
                    'dashboard.n_pending_time_sheet' => '{pendingActionsCount,plural, =0{Nenhuma folha de horas pendente} one{(1) Folha de Horas para Aprovar} other{(#) Folhas de Horas para Aprovar}}',
                    'dashboard.n_pending_performance_evaluate' => '{pendingActionsCount,plural, =0{Nenhuma avaliação de desempenho pendente} one{(1) Avaliação de Desempenho para Realizar} other{(#) Avaliações de Desempenho para Realizar}}',
                    'dashboard.n_pending_self_review' => '{pendingActionsCount,plural, =0{Nenhuma auto-avaliação pendente} one{(1) Auto-avaliação para Realizar} other{(#) Auto-avaliações para Realizar}}',

                    // Dropdowns / general
                    'general.current_employees_only' => 'Apenas funcionários atuais',
                    'general.current_and_past_employees' => 'Funcionários atuais e antigos',
                    'general.past_employees_only' => 'Apenas funcionários antigos',

                    // Months
                    'general.january' => 'Janeiro',
                    'general.february' => 'Fevereiro',
                    'general.march' => 'Março',
                    'general.april' => 'Abril',
                    'general.may' => 'Maio',
                    'general.june' => 'Junho',
                    'general.july' => 'Julho',
                    'general.august' => 'Agosto',
                    'general.september' => 'Setembro',
                    'general.october' => 'Outubro',
                    'general.november' => 'Novembro',
                    'general.december' => 'Dezembro',
                    'general.jan' => 'Jan',
                    'general.feb' => 'Fev',
                    'general.mar' => 'Mar',
                    'general.apr' => 'Abr',
                    'general.jun' => 'Jun',
                    'general.jul' => 'Jul',
                    'general.aug' => 'Ago',
                    'general.sep' => 'Set',
                    'general.oct' => 'Out',
                    'general.nov' => 'Nov',
                    'general.dec' => 'Dez',

                    // Days
                    'general.sun' => 'Dom',
                    'general.mon' => 'Seg',
                    'general.tue' => 'Ter',
                    'general.wed' => 'Qua',
                    'general.thu' => 'Qui',
                    'general.fri' => 'Sex',
                    'general.sat' => 'Sáb',
                    'general.sunday' => 'Domingo',
                    'general.monday' => 'Segunda-feira',
                    'general.tuesday' => 'Terça-feira',
                    'general.wednesday' => 'Quarta-feira',
                    'general.thursday' => 'Quinta-feira',
                    'general.friday' => 'Sexta-feira',
                    'general.saturday' => 'Sábado',
                ];

                foreach ($ptKeysDict as $k => $t) {
                    $data[$k] = [
                        'source' => isset($data[$k]['source']) ? $data[$k]['source'] : $k,
                        'target' => $t,
                    ];
                }

                foreach ($data as $key => &$entry) {
                    $source = $entry['source'] ?? '';
                    $target = $entry['target'] ?? '';

                    if (empty($target) || $target === $source) {
                        if (isset($ptDict[$source])) {
                            $entry['target'] = $ptDict[$source];
                        } elseif (isset($ptDict[$key])) {
                            $entry['target'] = $ptDict[$key];
                        } else {
                            $lastKeyPart = substr($key, strrpos($key, '.') ? strrpos($key, '.') + 1 : 0);
                            if (isset($ptDict[$lastKeyPart])) {
                                $entry['target'] = $ptDict[$lastKeyPart];
                            }
                        }
                    }
                }
                unset($entry);
                $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        $response = $this->getResponse();
        $response->setEtag(md5($json));

        if ($response->isNotModified($request)) {
            return $response;
        }

        $response->setContent($json);
        $this->setCommonHeaders($response, 'application/json');

        return $response;
    }

    /**
     * Accept common locale formats like "pt-PT" and normalize to "pt_PT".
     * Returns null if input is empty/invalid.
     *
     * @param mixed $locale
     */
    private function normalizeLocale($locale): ?string
    {
        if (!is_string($locale)) {
            return null;
        }
        $locale = trim($locale);
        if ($locale === '') {
            return null;
        }

        $locale = str_replace('-', '_', $locale);
        $parts = array_values(array_filter(explode('_', $locale)));
        if (count($parts) === 0) {
            return null;
        }

        $lang = strtolower($parts[0]);
        if (count($parts) === 1) {
            // Convention: "pt" defaults to Portugal.
            return $lang === 'pt' ? 'pt_PT' : $lang;
        }

        return $lang . '_' . strtoupper($parts[1]);
    }

    private function setCommonHeaders($response, string $contentType)
    {
        $response->headers->set('Content-Type', $contentType);
        $response->setPublic();
        $response->setMaxAge(Config::get(Config::MAX_SESSION_IDLE_TIME));
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->set('Pragma', 'Public');
    }
}
