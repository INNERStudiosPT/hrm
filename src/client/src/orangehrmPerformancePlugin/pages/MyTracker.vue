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
    <!-- Premium Glassmorphic KPI Cards -->
    <div v-if="kpiData" class="innerstudios-kpi-container">
      <div class="innerstudios-kpi-card --referrals">
        <div class="innerstudios-kpi-icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
          </svg>
        </div>
        <div class="innerstudios-kpi-info">
          <span class="innerstudios-kpi-value">{{ kpiData.referrals }}</span>
          <span class="innerstudios-kpi-label">Convites Realizados</span>
        </div>
      </div>

      <div class="innerstudios-kpi-card --tasks">
        <div class="innerstudios-kpi-icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
          </svg>
        </div>
        <div class="innerstudios-kpi-info">
          <span class="innerstudios-kpi-value">{{ kpiData.completed_tasks }}</span>
          <span class="innerstudios-kpi-label">Tarefas Concluídas</span>
        </div>
      </div>

      <div class="innerstudios-kpi-card --hours">
        <div class="innerstudios-kpi-icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="innerstudios-kpi-info">
          <span class="innerstudios-kpi-value">{{ kpiData.hours_done }}h</span>
          <span class="innerstudios-kpi-label">Horas de Turno</span>
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
  },
};
</script>

<style scoped>
.innerstudios-kpi-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
  font-family: 'Outfit', 'Inter', sans-serif;
}

.innerstudios-kpi-card {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  padding: 1.5rem 1.75rem;
  border-radius: 1.25rem;
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid rgba(255, 255, 255, 0.5);
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.04);
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
  position: relative;
  overflow: hidden;
}

.innerstudios-kpi-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
  z-index: 1;
  pointer-events: none;
}

.innerstudios-kpi-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.08);
}

.innerstudios-kpi-card.--referrals:hover {
  border-color: rgba(255, 123, 26, 0.4);
  box-shadow: 0 12px 40px 0 rgba(255, 123, 26, 0.1);
}

.innerstudios-kpi-card.--tasks:hover {
  border-color: rgba(139, 92, 246, 0.4);
  box-shadow: 0 12px 40px 0 rgba(139, 92, 246, 0.1);
}

.innerstudios-kpi-card.--hours:hover {
  border-color: rgba(16, 185, 129, 0.4);
  box-shadow: 0 12px 40px 0 rgba(16, 185, 129, 0.1);
}

.innerstudios-kpi-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: 1rem;
  z-index: 2;
  transition: transform 0.3s ease;
}

.innerstudios-kpi-card:hover .innerstudios-kpi-icon {
  transform: scale(1.1);
}

.--referrals .innerstudios-kpi-icon {
  background: rgba(255, 123, 26, 0.1);
  color: #ff7b1a;
}

.--tasks .innerstudios-kpi-icon {
  background: rgba(139, 92, 246, 0.1);
  color: #8b5cf6;
}

.--hours .innerstudios-kpi-icon {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.innerstudios-kpi-icon svg {
  width: 1.75rem;
  height: 1.75rem;
}

.innerstudios-kpi-info {
  display: flex;
  flex-direction: column;
  z-index: 2;
}

.innerstudios-kpi-value {
  font-size: 1.75rem;
  font-weight: 800;
  line-height: 1.2;
  color: #1f2937;
  letter-spacing: -0.02em;
}

.--referrals .innerstudios-kpi-value {
  color: #e06000;
}

.--tasks .innerstudios-kpi-value {
  color: #7c3aed;
}

.--hours .innerstudios-kpi-value {
  color: #059669;
}

.innerstudios-kpi-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 0.25rem;
}
</style>
