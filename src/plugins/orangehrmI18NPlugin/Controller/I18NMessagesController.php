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

        $response = $this->getResponse();
        try {
            $response->setEtag($this->getI18NService()->getETagByLangCode($locale));
        } catch (InvalidArgumentException $e) {
            $locale = $this->getConfigService()->getAdminLocalizationDefaultLanguage();
            $response->setEtag($this->getI18NService()->getETagByLangCode($locale));
        }

        if (!$response->isNotModified($request)) {
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

            $response->setContent($json);
            $this->setCommonHeaders($response, 'application/json');
        }

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
