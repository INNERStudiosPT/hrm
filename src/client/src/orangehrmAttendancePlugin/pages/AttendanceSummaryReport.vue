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
  <reports-table
    ref="reportsTableRef"
    module="time"
    name="attendance"
    :prefetch="false"
    :filters="serializedFilters"
    :column-count="2"
  >
    <template #default="{generateReport}">
      <oxd-table-filter
        :filter-title="$t('attendance.attendance_total_summary_report')"
      >
        <oxd-form @submit-valid="generateReport">
          <oxd-form-row>
            <oxd-grid :cols="3" class="orangehrm-full-width-grid">
              <oxd-grid-item>
                <employee-autocomplete
                  v-model="filters.employee"
                  :rules="rules.employee"
                  :params="{
                    includeEmployees: 'currentAndPast',
                  }"
                />
              </oxd-grid-item>

              <oxd-grid-item>
                <jobtitle-dropdown v-model="filters.jobTitle" />
              </oxd-grid-item>
              <oxd-grid-item>
                <oxd-input-field
                  v-model="filters.subunit"
                  type="select"
                  :label="$t('general.sub_unit')"
                  :options="subunits"
                />
              </oxd-grid-item>
              <oxd-grid-item>
                <employment-status-dropdown v-model="filters.empStatus" />
              </oxd-grid-item>
              <oxd-grid-item>
                <date-input
                  v-model="filters.fromDate"
                  :placeholder="$t('general.from')"
                  :rules="rules.fromDate"
                  :label="$t('general.date_range')"
                />
              </oxd-grid-item>
              <oxd-grid-item>
                <date-input
                  v-model="filters.toDate"
                  label="&nbsp"
                  :placeholder="$t('general.to')"
                  :rules="rules.toDate"
                />
              </oxd-grid-item>
            </oxd-grid>
          </oxd-form-row>

          <oxd-divider />

          <oxd-form-actions>
            <oxd-button
              type="submit"
              display-type="secondary"
              :label="$t('general.view')"
            />
            <oxd-button
              v-if="hasData"
              type="button"
              display-type="ghost"
              label="Exportar Excel"
              @click="exportToExcel"
              style="margin-left: 8px; border: 1px solid #4caf50; color: #4caf50;"
            />
            <oxd-button
              v-if="hasData"
              type="button"
              display-type="ghost"
              label="Exportar PDF"
              @click="exportToPDF"
              style="margin-left: 8px; border: 1px solid #f44336; color: #f44336;"
            />
          </oxd-form-actions>
        </oxd-form>
      </oxd-table-filter>
      <br />
    </template>

    <template #footer="{data}">
      {{ $t('time.total_duration') }}:
      {{ data.meta ? data.meta.sum.label : '0.00' }}
    </template>
  </reports-table>
</template>

<script>
import {computed, ref} from 'vue';
import {
  validSelection,
  validDateFormat,
  endDateShouldBeAfterStartDate,
  startDateShouldBeBeforeEndDate,
  shouldNotExceedCharLength,
} from '@/core/util/validation/rules';
import ReportsTable from '@/core/components/table/ReportsTable';
import JobtitleDropdown from '@/orangehrmPimPlugin/components/JobtitleDropdown';
import EmployeeAutocomplete from '@/core/components/inputs/EmployeeAutocomplete';
import EmploymentStatusDropdown from '@/orangehrmPimPlugin/components/EmploymentStatusDropdown';
import usei18n from '@/core/util/composable/usei18n';
import useDateFormat from '@/core/util/composable/useDateFormat';

const defaultFilters = {
  employee: null,
  fromDate: null,
  toDate: null,
  jobTitle: null,
  subunit: null,
  empStatus: null,
};

export default {
  components: {
    'reports-table': ReportsTable,
    'jobtitle-dropdown': JobtitleDropdown,
    'employee-autocomplete': EmployeeAutocomplete,
    'employment-status-dropdown': EmploymentStatusDropdown,
  },

  props: {
    subunits: {
      type: Array,
      default: () => [],
    },
  },

  setup() {
    const reportsTableRef = ref(null);
    const filters = ref({
      ...defaultFilters,
    });
    const {$t} = usei18n();
    const {userDateFormat} = useDateFormat();

    const rules = {
      fromDate: [
        validDateFormat(userDateFormat),
        startDateShouldBeBeforeEndDate(
          () => filters.value.toDate,
          $t('general.from_date_should_be_before_to_date'),
          {allowSameDate: true},
        ),
      ],
      toDate: [
        validDateFormat(userDateFormat),
        endDateShouldBeAfterStartDate(
          () => filters.value.fromDate,
          $t('general.to_date_should_be_after_from_date'),
          {allowSameDate: true},
        ),
      ],
      employee: [shouldNotExceedCharLength(100), validSelection],
    };

    const serializedFilters = computed(() => {
      return {
        empNumber: filters.value.employee?.id,
        fromDate: filters.value.fromDate,
        toDate: filters.value.toDate,
        jobTitleId: filters.value.jobTitle?.id,
        subunitId: filters.value.subunit?.id,
        employmentStatusId: filters.value.empStatus?.id,
      };
    });

    const hasData = computed(() => {
      return !!(
        reportsTableRef.value &&
        reportsTableRef.value.items &&
        reportsTableRef.value.items.length > 0
      );
    });

    const exportToExcel = () => {
      if (!reportsTableRef.value || !reportsTableRef.value.items || reportsTableRef.value.items.length === 0) {
        return;
      }
      const items = reportsTableRef.value.items;
      const headers = reportsTableRef.value.headers;
      
      const headerRow = headers.map(h => `"${h.name.replace(/"/g, '""')}"`).join(',');
      const dataRows = items.map(item => {
        return headers.map(h => {
          const val = item[h.element || h.prop] || '';
          return `"${String(val).replace(/"/g, '""')}"`;
        }).join(',');
      });
      
      const csvContent = "\uFEFF" + [headerRow, ...dataRows].join('\n');
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', 'relatorio_sumario_assiduidade.csv');
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    };

    const exportToPDF = () => {
      if (!reportsTableRef.value || !reportsTableRef.value.items || reportsTableRef.value.items.length === 0) {
        return;
      }
      const items = reportsTableRef.value.items;
      const headers = reportsTableRef.value.headers;
      const totalDuration = reportsTableRef.value.response?.meta?.sum?.label || '0.00';
      
      const printWindow = window.open('', '_blank');
      
      let tableHeadersHtml = '';
      headers.forEach(h => {
        tableHeadersHtml += `<th style="padding: 12px; border-bottom: 2px solid #e2e8f0; text-align: left; font-weight: 600; color: #4a5568;">${h.name}</th>`;
      });
      
      let tableRowsHtml = '';
      items.forEach(item => {
        tableRowsHtml += '<tr style="border-bottom: 1px solid #edf2f7;">';
        headers.forEach(h => {
          const val = item[h.element || h.prop] || '';
          tableRowsHtml += `<td style="padding: 12px; color: #2d3748;">${val}</td>`;
        });
        tableRowsHtml += '</tr>';
      });
      
      const dateRange = (filters.value.fromDate || '') + (filters.value.toDate ? ` a ${filters.value.toDate}` : '');
      
      const htmlContent = `
        <html>
          <head>
            <title>Relatório Sumário de Assiduidade</title>
            <style>
              body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                margin: 40px;
                color: #2d3748;
              }
              .header {
                margin-bottom: 30px;
                border-bottom: 2px solid #319795;
                padding-bottom: 15px;
              }
              .title {
                font-size: 24px;
                font-weight: 700;
                color: #1a202c;
                margin: 0;
              }
              .subtitle {
                font-size: 14px;
                color: #718096;
                margin-top: 5px;
              }
              .info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
                font-size: 12px;
                color: #4a5568;
              }
              table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 30px;
              }
              .footer {
                display: flex;
                justify-content: flex-end;
                font-size: 16px;
                font-weight: 700;
                color: #1a202c;
                border-top: 2px solid #edf2f7;
                padding-top: 15px;
              }
              @media print {
                body { margin: 20px; }
              }
            </style>
          </head>
          <body>
            <div class="header">
              <div class="title">Relatório Sumário de Assiduidade</div>
              <div class="subtitle">OrangeHRM - INNER Studios</div>
            </div>
            <div class="info">
              <div><strong>Filtro de Período:</strong> ${dateRange || 'Todo o período'}</div>
              <div><strong>Data de Emissão:</strong> ${new Date().toLocaleDateString('pt-PT')}</div>
            </div>
            <table>
              <thead>
                <tr>${tableHeadersHtml}</tr>
              </thead>
              <tbody>
                ${tableRowsHtml}
              </tbody>
            </table>
            <div class="footer">
              Duração Total: ${totalDuration} Horas
            </div>
            <script>
              window.onload = function() {
                window.print();
                setTimeout(function() { window.close(); }, 500);
              };
            <\/script>
          </body>
        </html>
      `;
      
      printWindow.document.write(htmlContent);
      printWindow.document.close();
    };

    return {
      rules,
      filters,
      serializedFilters,
      reportsTableRef,
      hasData,
      exportToExcel,
      exportToPDF,
    };
  },
};
</script>

