<!--
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
 -->


<template>
  <div class="orangehrm-background-container">
    <!-- Premium Glassmorphic Performance Dashboard -->
    <div v-if="kpiData" class="innerstudios-dashboard">
      <oxd-text tag="h5" class="innerstudios-dashboard-title">
        Painel de Performance & KPIs
      </oxd-text>

      <!-- Grid for Key Metrics -->
      <div class="innerstudios-kpi-grid">
        <!-- Card: Turnos e Assiduidade -->
        <div class="innerstudios-dash-card">
          <div class="innerstudios-card-header">
            <svg class="icon --orange" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h6>Turnos e Horas</h6>
          </div>
          <div class="innerstudios-card-body">
            <div class="kpi-row">
              <span class="label">Horas Realizadas</span>
              <span class="value --orange">{{ kpiData.hours_done }}h</span>
            </div>
            <div class="kpi-row">
              <span class="label">Turnos Efetuados</span>
              <span class="value">{{ kpiData.total_shifts }}</span>
            </div>
            <div class="kpi-row">
              <span class="label">Duração Média</span>
              <span class="value">{{ kpiData.avg_shift_length }}h</span>
            </div>
            <div class="kpi-row">
              <span class="label">Conformidade de Descanso</span>
              <span class="value" :class="kpiData.rest_compliance_score === 100 ? 'text-success' : 'text-warning'">
                {{ kpiData.rest_compliance_score }}%
              </span>
            </div>
          </div>
        </div>

        <!-- Card: Tarefas e Produtividade -->
        <div class="innerstudios-dash-card">
          <div class="innerstudios-card-header">
            <svg class="icon --purple" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <h6>Tarefas e Produtividade</h6>
          </div>
          <div class="innerstudios-card-body">
            <div class="kpi-row">
              <span class="label">Concluídas</span>
              <span class="value --purple">{{ kpiData.completed_tasks }}</span>
            </div>
            <div class="kpi-row">
              <span class="label">Pendente (Backlog)</span>
              <span class="value">{{ kpiData.active_backlog }}</span>
            </div>
            <div class="kpi-row">
              <span class="label">Taxa de Prazo (On-Time)</span>
              <span class="value">{{ kpiData.on_time_rate }}%</span>
            </div>
            <div class="kpi-row">
              <span class="label">Velocidade (Média)</span>
              <span class="value">
                {{ kpiData.resolution_velocity.High || kpiData.resolution_velocity.Medium || kpiData.resolution_velocity['3'] || kpiData.resolution_velocity['2'] || '0' }}h / tarefa
              </span>
            </div>
          </div>
        </div>

        <!-- Card: Crescimento & Referrals -->
        <div class="innerstudios-dash-card">
          <div class="innerstudios-card-header">
            <svg class="icon --teal" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            <h6>Crescimento & Convites</h6>
          </div>
          <div class="innerstudios-card-body">
            <div class="kpi-row">
              <span class="label">Convites Realizados</span>
              <span class="value --teal">{{ kpiData.referrals }}</span>
            </div>
            <div class="kpi-row">
              <span class="label">Contratados (Hired)</span>
              <span class="value">{{ kpiData.referrals_hired }}</span>
            </div>
            <div class="kpi-row">
              <span class="label">Taxa de Conversão</span>
              <span class="value">{{ kpiData.referrals_conversion_rate }}%</span>
            </div>
            <div class="kpi-row">
              <span class="label">Triagem / Pipeline</span>
              <span class="value">{{ kpiData.referral_stages.pending || kpiData.referral_stages.interview || 0 }} ativas</span>
            </div>
          </div>
        </div>

        <!-- Card: Alocação de Tempo & Projetos -->
        <div class="innerstudios-dash-card">
          <div class="innerstudios-card-header">
            <svg class="icon --indigo" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
            </svg>
            <h6>Projetos & Utilização</h6>
          </div>
          <div class="innerstudios-card-body">
            <div class="kpi-row">
              <span class="label">Utilização (InnerFX)</span>
              <span class="value --indigo">{{ kpiData.utility_rate }}%</span>
            </div>
            
            <div class="projects-list">
              <div v-for="proj in kpiData.projects_distribution" :key="proj.name" class="proj-item">
                <div class="proj-info">
                  <span class="proj-name">{{ proj.name }}</span>
                  <span class="proj-hours">{{ proj.hours }}h</span>
                </div>
                <div class="proj-progress-bar">
                  <div class="bar-fill" :style="{ width: calcProjPercent(proj.hours) + '%' }"></div>
                </div>
              </div>
              <div v-if="!kpiData.projects_distribution || kpiData.projects_distribution.length === 0" class="text-muted text-center pt-2">
                Nenhum tempo alocado em projetos.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Row: Wellbeing -->
      <div class="innerstudios-dashboard-bottom">
        <div class="wellbeing-banner">
          <div class="wellbeing-info">
            <svg class="icon --green" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
            <div class="wellbeing-text">
              <h6>Equilíbrio Laboral & Descanso</h6>
              <p>Goza as suas férias e folgas regularmente para manter uma excelente saúde física e mental.</p>
            </div>
          </div>
          <div class="wellbeing-metrics">
            <div class="wellbeing-metric-card">
              <span class="val">{{ kpiData.leaves_taken }} dias</span>
              <span class="lbl">Férias Gozadas</span>
            </div>
            <div class="wellbeing-metric-card">
              <span class="val">{{ kpiData.leaves_entitled }} dias</span>
              <span class="lbl">Férias Atribuídas</span>
            </div>
            <div class="wellbeing-metric-card">
              <span class="val">{{ kpiData.leave_burn_rate }}%</span>
              <span class="lbl">Taxa de Queima</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="orangehrm-paper-container">
      <div class="orangehrm-header-container">
        <oxd-text tag="h6" class="orangehrm-main-title">
          {{ $t('performance.my_performance_trackers') }}
        </oxd-text>
      </div>
      <table-header :selected="0" :total="total" :loading="isLoading">
      </table-header>
      <div class="orangehrm-container">
        <oxd-card-table
          v-model:order="sortDefinition"
          :headers="headers"
          :items="items?.data"
          :loading="isLoading"
          row-decorator="oxd-table-decorator-card"
        />
      </div>
      <div class="orangehrm-bottom-container">
        <oxd-pagination
          v-if="showPaginator"
          v-model:current="currentPage"
          :length="pages"
        />
      </div>
    </div>
  </div>
</template>
<script>
import {computed} from 'vue';
import {navigate} from '@/core/util/helper/navigation';
import {APIService} from '@/core/util/services/api.service';
import usePaginate from '@ohrm/core/util/composable/usePaginate';
import useSort from '@ohrm/core/util/composable/useSort';
import {formatDate, parseDate} from '@ohrm/core/util/helper/datefns';
import useDateFormat from '@/core/util/composable/useDateFormat';
import useLocale from '@/core/util/composable/useLocale';

const defaultSortOrder = {
  'performanceTracker.trackerName': 'DEFAULT',
  'performanceTracker.addedDate': 'DEFAULT',
  'performanceTracker.modifiedDate': 'DESC',
};

export default {
  setup() {
    const {sortDefinition, sortField, sortOrder, onSort} = useSort({
      sortDefinition: defaultSortOrder,
    });

    const serializedFilter = computed(() => {
      return {
        sortField: sortField.value,
        sortOrder: sortOrder.value,
      };
    });

    const http = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/performance/trackers',
    );
    const {jsDateFormat} = useDateFormat();
    const {locale} = useLocale();

    const trackerNormalizer = (data) => {
      return data.map((item) => {
        return {
          id: item.id,
          tracker: item.trackerName,
          addedDate: formatDate(parseDate(item.addedDate), jsDateFormat, {
            locale,
          }),
          modifiedDate: formatDate(parseDate(item.modifiedDate), jsDateFormat, {
            locale,
          }),
        };
      });
    };

    const {
      currentPage,
      total,
      showPaginator,
      pages,
      pageSize,
      response,
      execQuery,
      isLoading,
    } = usePaginate(http, {
      query: serializedFilter,
      normalizer: trackerNormalizer,
    });

    onSort(execQuery);

    return {
      http,
      total,
      isLoading,
      items: response,
      execQuery,
      sortDefinition,
      showPaginator,
      pages,
      pageSize,
      currentPage,
    };
  },

  data() {
    return {
      kpiData: null,
      headers: [
        {
          name: 'tracker',
          slot: 'title',
          title: this.$t('performance.tracker'),
          sortField: 'performanceTracker.trackerName',
          style: {flex: '30%'},
        },
        {
          name: 'addedDate',
          title: this.$t('performance.added_date'),
          sortField: 'performanceTracker.addedDate',
          style: {flex: 1},
        },
        {
          name: 'modifiedDate',
          title: this.$t('performance.modified_date'),
          sortField: 'performanceTracker.modifiedDate',
          style: {flex: 1},
        },
        {
          name: 'action',
          slot: 'action',
          title: this.$t('general.actions'),
          style: {flex: 1},
          cellType: 'oxd-table-cell-actions',
          cellConfig: {
            view: {
              onClick: this.onClickView,
              component: 'oxd-button',
              props: {
                name: 'view',
                label: this.$t('general.view'),
                displayType: 'text',
              },
            },
          },
        },
      ],
    };
  },

  beforeMount() {
    const myselfHttp = new APIService(window.appGlobal.baseUrl, '/api/v2/pim/myself');
    myselfHttp.getAll()
      .then((res) => {
        if (res && res.data && res.data.data) {
          const empNumber = res.data.data.empNumber;
          const contactHttp = new APIService(
            window.appGlobal.baseUrl,
            `/api/v2/pim/employees/${empNumber}/contact-details`
          );
          return contactHttp.getAll().then((contactRes) => {
            if (contactRes && contactRes.data && contactRes.data.data) {
              const cData = contactRes.data.data;
              const email = cData.workEmail || cData.otherEmail || res.data.data.employeeId;
              if (email) {
                return fetch(`https://api.innerstudios.pt/v1/public/performance-kpis/${encodeURIComponent(email)}`)
                  .then(kpiRes => kpiRes.json())
                  .then(kpiData => {
                    if (kpiData && kpiData.resolved) {
                      this.kpiData = kpiData;
                    }
                  });
              }
            }
          });
        }
      })
      .catch((err) => {
        console.error('[MyTracker] Error loading custom KPIs:', err);
      });
  },

  methods: {
    onClickView(item) {
      navigate('/performance/addPerformanceTrackerLog/trackId/{id}?mode=my', {
        id: item.id,
      });
    },
    calcProjPercent(hours) {
      if (!this.kpiData || !this.kpiData.projects_distribution) return 0;
      const total = this.kpiData.projects_distribution.reduce((acc, curr) => acc + curr.hours, 0);
      return total > 0 ? (hours / total * 100) : 0;
    }
  },
};
</script>

<style scoped>
.innerstudios-dashboard {
  margin-bottom: 2.5rem;
  font-family: 'Outfit', 'Inter', sans-serif;
}

.innerstudios-dashboard-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: #1f2937;
  margin-bottom: 1.5rem;
  letter-spacing: -0.02em;
}

.innerstudios-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.innerstudios-dash-card {
  border-radius: 1.25rem;
  background: rgba(255, 255, 255, 0.75);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.5);
  box-shadow: 0 10px 30px 0 rgba(31, 38, 135, 0.04);
  padding: 1.5rem;
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
  display: flex;
  flex-direction: column;
}

.innerstudios-dash-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 35px 0 rgba(31, 38, 135, 0.08);
}

.innerstudios-card-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
  padding-bottom: 0.75rem;
}

.innerstudios-card-header h6 {
  font-size: 1rem;
  font-weight: 700;
  color: #374151;
  margin: 0;
}

.innerstudios-card-header .icon {
  width: 1.5rem;
  height: 1.5rem;
}

.icon.--orange { color: #ff7b1a; }
.icon.--purple { color: #8b5cf6; }
.icon.--teal { color: #14b8a6; }
.icon.--indigo { color: #6366f1; }
.icon.--green { color: #10b981; }

.innerstudios-card-body {
  display: flex;
  flex-direction: column;
  gap: 0.875rem;
  flex: 1;
}

.kpi-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kpi-row .label {
  font-size: 0.825rem;
  color: #6b7280;
  font-weight: 500;
}

.kpi-row .value {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1f2937;
}

.kpi-row .value.--orange { color: #e06000; }
.kpi-row .value.--purple { color: #7c3aed; }
.kpi-row .value.--teal { color: #0f766e; }
.kpi-row .value.--indigo { color: #4338ca; }

.text-success { color: #10b981 !important; font-weight: 700; }
.text-warning { color: #f59e0b !important; font-weight: 700; }
.text-muted { color: #9ca3af !important; }
.text-center { text-align: center; }
.pt-2 { padding-top: 0.5rem; }

.projects-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.proj-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.proj-info {
  display: flex;
  justify-content: space-between;
  font-size: 0.775rem;
  font-weight: 600;
  color: #4b5563;
}

.proj-progress-bar {
  height: 6px;
  background: rgba(0, 0, 0, 0.05);
  border-radius: 99px;
  overflow: hidden;
}

.bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #6366f1 0%, #818cf8 100%);
  border-radius: 99px;
  transition: width 0.8s ease;
}

/* Wellbeing Banner styling */
.innerstudios-dashboard-bottom {
  margin-top: 1.5rem;
}

.wellbeing-banner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 1.5rem 2rem;
  border-radius: 1.25rem;
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.04) 0%, rgba(5, 150, 105, 0.08) 100%);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(16, 185, 129, 0.15);
}

.wellbeing-info {
  display: flex;
  align-items: center;
  gap: 1rem;
  max-width: 32rem;
}

.wellbeing-info .icon {
  width: 2.5rem;
  height: 2.5rem;
  flex-shrink: 0;
}

.wellbeing-text h6 {
  font-size: 1.05rem;
  font-weight: 700;
  color: #065f46;
  margin: 0 0 0.25rem 0;
}

.wellbeing-text p {
  font-size: 0.825rem;
  color: #374151;
  margin: 0;
  line-height: 1.4;
}

.wellbeing-metrics {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.wellbeing-metric-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 0.75rem 1.25rem;
  background: rgba(255, 255, 255, 0.6);
  border: 1px solid rgba(16, 185, 129, 0.1);
  border-radius: 0.875rem;
  min-width: 6.5rem;
}

.wellbeing-metric-card .val {
  font-size: 1.15rem;
  font-weight: 800;
  color: #047857;
}

.wellbeing-metric-card .lbl {
  font-size: 0.675rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 0.25rem;
}
</style>
