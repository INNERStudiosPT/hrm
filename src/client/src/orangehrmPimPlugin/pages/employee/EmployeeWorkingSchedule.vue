<!--
/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 */
 -->

<template>
  <edit-employee-layout
    screen="working-schedule"
    :employee-id="empNumber"
    :tabs="tabs"
    :allowed-file-types="allowedFileTypes"
    :max-file-size="maxFileSize"
  >
    <div
      class="orangehrm-horizontal-padding orangehrm-vertical-padding working-schedule-container"
    >
      <oxd-text tag="h6" class="orangehrm-main-title">
        {{ $t('pim.working_schedule') || 'Working Schedule' }}
      </oxd-text>
      <oxd-divider />

      <div v-if="isLoading" class="working-schedule-loading">
        <oxd-text tag="p">{{ $t('general.loading') || 'Loading...' }}</oxd-text>
      </div>

      <div v-else class="working-schedule-content">
        <!-- Section 1: Current Shift Display -->
        <div class="schedule-card current-shift-card">
          <div class="card-header-icon">
            <oxd-icon name="clock" />
          </div>
          <div class="card-details">
            <oxd-text tag="h5" class="card-title">
              {{
                $t('working_schedule.current_assigned_shift') ||
                'Current Assigned Shift'
              }}
            </oxd-text>
            <div v-if="currentShift" class="shift-info">
              <oxd-text tag="p" class="shift-name">{{
                currentShift.name
              }}</oxd-text>
              <oxd-text tag="p" class="shift-hours">
                {{ currentShift.startTime }} - {{ currentShift.endTime }}
                <span class="hours-badge"
                  >({{ currentShift.hoursPerDay }}
                  {{ $t('working_schedule.hours_day') || 'hrs/day' }})</span
                >
              </oxd-text>
            </div>
            <div v-else class="no-shift-info">
              <oxd-text tag="p" class="info-alert-text">
                {{
                  $t('working_schedule.no_shift_assigned') ||
                  'No working shift assigned yet.'
                }}
              </oxd-text>
            </div>
          </div>
        </div>

        <!-- Section 2: Admin Configurations -->
        <div v-if="isAdmin" class="schedule-card admin-config-card">
          <div class="card-details">
            <oxd-text tag="h5" class="card-title">
              {{ $t('working_schedule.configurations') || 'Configurations' }}
            </oxd-text>
            <oxd-form-row class="switch-row">
              <oxd-text tag="p" class="switch-label">
                {{
                  $t('working_schedule.allow_employee_customization') ||
                  'Defined by worker (Allow customization)'
                }}
              </oxd-text>
              <oxd-switch-input
                v-model="isCustomizable"
                @update:model-value="toggleCustomizable"
              />
            </oxd-form-row>
            <oxd-text tag="p" class="config-help-text">
              {{
                $t('working_schedule.config_help') ||
                'Enable this option to allow the employee to submit work schedule change requests for your approval.'
              }}
            </oxd-text>
          </div>
        </div>

        <!-- Section 3: Pending Request Area -->
        <div v-if="pendingRequest" class="schedule-card pending-request-card">
          <div class="card-header-icon pending-icon">
            <oxd-icon name="history" />
          </div>
          <div class="card-details">
            <div class="pending-badge-header">
              <oxd-text tag="h5" class="card-title">
                {{
                  $t('working_schedule.pending_request') ||
                  'Pending Change Request'
                }}
              </oxd-text>
              <span class="status-badge --pending">{{
                $t('working_schedule.status_pending') || 'Pending Approval'
              }}</span>
            </div>

            <div class="request-details-grid">
              <div class="request-detail-item">
                <oxd-text tag="p" class="detail-label">{{
                  $t('working_schedule.proposed_shift') || 'Proposed Shift'
                }}</oxd-text>
                <oxd-text tag="p" class="detail-value">
                  {{ pendingRequest.workShift.name }} ({{
                    pendingRequest.workShift.startTime
                  }}
                  - {{ pendingRequest.workShift.endTime }})
                </oxd-text>
              </div>
              <div class="request-detail-item">
                <oxd-text tag="p" class="detail-label">{{
                  $t('working_schedule.submitted_on') || 'Submitted On'
                }}</oxd-text>
                <oxd-text tag="p" class="detail-value">{{
                  pendingRequest.createdAt
                }}</oxd-text>
              </div>
              <div
                v-if="pendingRequest.reason"
                class="request-detail-item --full-width"
              >
                <oxd-text tag="p" class="detail-label">{{
                  $t('working_schedule.reason') || 'Reason / Justification'
                }}</oxd-text>
                <oxd-text tag="p" class="detail-value reason-text">{{
                  pendingRequest.reason
                }}</oxd-text>
              </div>
            </div>

            <!-- Admin Actions to Resolve Request -->
            <div v-if="isAdmin" class="admin-resolve-actions">
              <oxd-button
                type="button"
                display-type="success"
                :label="$t('general.approve') || 'Approve'"
                :loading="isSubmitting"
                @click="resolveRequest('approved')"
              />
              <oxd-button
                type="button"
                display-type="danger"
                class="orangehrm-left-space"
                :label="$t('general.reject') || 'Reject'"
                :loading="isSubmitting"
                @click="resolveRequest('rejected')"
              />
            </div>
            <div v-else class="employee-pending-note">
              <oxd-text tag="p">
                {{
                  $t('working_schedule.employee_pending_note') ||
                  'Your request is currently being reviewed by an administrator.'
                }}
              </oxd-text>
            </div>
          </div>
        </div>

        <!-- Section 4: Employee Submit Change Request Form -->
        <div
          v-if="isSelf && isCustomizable && !pendingRequest"
          class="schedule-card request-form-card"
        >
          <oxd-text tag="h5" class="card-title">
            {{
              $t('working_schedule.request_change_title') ||
              'Submit Schedule Change Request'
            }}
          </oxd-text>
          <oxd-form @submit-valid="submitRequest">
            <oxd-form-row>
              <oxd-grid :cols="2" class="orangehrm-full-width-grid">
                <oxd-grid-item>
                  <oxd-input-field
                    v-model="newRequest.workShiftId"
                    type="select"
                    :label="
                      $t('working_schedule.preferred_shift') ||
                      'Preferred Work Shift'
                    "
                    :options="normalizedShifts"
                    :rules="[rules.required]"
                  />
                </oxd-grid-item>
              </oxd-grid>
            </oxd-form-row>

            <oxd-form-row>
              <oxd-grid :cols="1" class="orangehrm-full-width-grid">
                <oxd-grid-item>
                  <oxd-input-field
                    v-model="newRequest.reason"
                    type="textarea"
                    :label="
                      $t('working_schedule.reason_label') ||
                      'Reason / Justification'
                    "
                    :placeholder="
                      $t('working_schedule.reason_placeholder') ||
                      'Provide details about why you need this schedule change...'
                    "
                    :rules="[rules.required, rules.maxLength]"
                  />
                </oxd-grid-item>
              </oxd-grid>
            </oxd-form-row>

            <oxd-divider />

            <oxd-form-actions>
              <oxd-button
                type="submit"
                display-type="secondary"
                :label="$t('general.submit') || 'Submit Request'"
                :loading="isSubmitting"
              />
            </oxd-form-actions>
          </oxd-form>
        </div>

        <!-- Section 5: Schedule Locked Alert for non-customizable employees -->
        <div
          v-if="isSelf && !isCustomizable && !pendingRequest"
          class="schedule-card locked-schedule-card"
        >
          <div class="card-header-icon locked-icon">
            <oxd-icon name="lock" />
          </div>
          <div class="card-details">
            <oxd-text tag="h5" class="card-title locked-title">
              {{
                $t('working_schedule.locked_title') ||
                'Schedule Managed by Administrator'
              }}
            </oxd-text>
            <oxd-text tag="p" class="locked-desc">
              {{
                $t('working_schedule.locked_desc') ||
                'Your work schedule customization is currently disabled. Please contact your administrator if you need to make any adjustments to your working hours.'
              }}
            </oxd-text>
          </div>
        </div>
      </div>
    </div>
  </edit-employee-layout>
</template>

<script>
import {APIService} from '@ohrm/core/util/services/api.service';
import EditEmployeeLayout from '@/orangehrmPimPlugin/components/EditEmployeeLayout';
import {OxdSwitchInput} from '@ohrm/oxd';
import {
  required,
  shouldNotExceedCharLength,
} from '@ohrm/core/util/validation/rules';

export default {
  name: 'EmployeeWorkingSchedule',
  components: {
    'edit-employee-layout': EditEmployeeLayout,
    'oxd-switch-input': OxdSwitchInput,
  },
  props: {
    empNumber: {
      type: [Number, String],
      required: true,
    },
    tabs: {
      type: Array,
      default: () => [],
    },
    allowedFileTypes: {
      type: Array,
      default: () => [],
    },
    maxFileSize: {
      type: Number,
      default: 0,
    },
  },
  setup(props) {
    const http = new APIService(
      window.appGlobal.baseUrl,
      `/api/v2/pim/employees/${props.empNumber}/working-schedule`,
    );
    return {
      http,
    };
  },
  data() {
    return {
      isLoading: false,
      isSubmitting: false,
      isSelf: false,
      isAdmin: false,
      isCustomizable: false,
      currentShift: null,
      availableShifts: [],
      pendingRequest: null,
      newRequest: {
        workShiftId: null,
        reason: '',
      },
      rules: {
        required: required,
        maxLength: shouldNotExceedCharLength(255),
      },
    };
  },
  computed: {
    normalizedShifts() {
      return this.availableShifts.map((shift) => ({
        id: shift.id,
        label: `${shift.name} (${shift.startTime} - ${shift.endTime})`,
      }));
    },
  },
  beforeMount() {
    this.fetchData();
  },
  methods: {
    fetchData() {
      this.isLoading = true;
      this.http
        .get()
        .then((response) => {
          const {data} = response.data;
          this.isSelf = data.isSelf;
          this.isAdmin = data.isAdmin;
          this.isCustomizable = data.isCustomizable;
          this.currentShift = data.currentShift;
          this.availableShifts = data.availableShifts || [];
          this.pendingRequest = data.pendingRequest;
        })
        .catch((error) => {
          /* eslint-disable-next-line no-console */
          console.error('Error fetching working schedule data:', error);
          this.$toast.error(
            this.$t('working_schedule.fetch_error') ||
              'Failed to load working schedule.',
          );
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    toggleCustomizable() {
      this.isSubmitting = true;
      this.http
        .request({
          method: 'PUT',
          url: `/api/v2/pim/employees/${this.empNumber}/working-schedule/config`,
          data: {
            isCustomizable: this.isCustomizable,
          },
        })
        .then(() => {
          this.$toast.success(
            this.$t('working_schedule.config_updated') ||
              'Configuration updated successfully.',
          );
          this.fetchData();
        })
        .catch((error) => {
          /* eslint-disable-next-line no-console */
          console.error('Error updating configuration:', error);
          this.$toast.error(
            this.$t('working_schedule.config_error') ||
              'Failed to update configuration.',
          );
          this.isCustomizable = !this.isCustomizable; // Revert change
        })
        .finally(() => {
          this.isSubmitting = false;
        });
    },
    submitRequest() {
      this.isSubmitting = true;
      this.http
        .request({
          method: 'POST',
          url: `/api/v2/pim/employees/${this.empNumber}/working-schedule/request`,
          data: {
            workShiftId:
              this.newRequest.workShiftId?.id || this.newRequest.workShiftId,
            reason: this.newRequest.reason,
          },
        })
        .then(() => {
          this.$toast.success(
            this.$t('working_schedule.request_submitted') ||
              'Schedule change request submitted.',
          );
          this.newRequest.workShiftId = null;
          this.newRequest.reason = '';
          this.fetchData();
        })
        .catch((error) => {
          /* eslint-disable-next-line no-console */
          console.error('Error submitting request:', error);
          this.$toast.error(
            this.$t('working_schedule.submit_error') ||
              'Failed to submit request.',
          );
        })
        .finally(() => {
          this.isSubmitting = false;
        });
    },
    resolveRequest(status) {
      if (!this.pendingRequest) return;
      this.isSubmitting = true;
      this.http
        .request({
          method: 'PUT',
          url: `/api/v2/pim/employees/${this.empNumber}/working-schedule/request/${this.pendingRequest.id}`,
          data: {
            status: status,
          },
        })
        .then(() => {
          const msg =
            status === 'approved'
              ? this.$t('working_schedule.request_approved') ||
                'Request approved and work shift updated successfully.'
              : this.$t('working_schedule.request_rejected') ||
                'Request rejected successfully.';
          this.$toast.success(msg);
          this.fetchData();
        })
        .catch((error) => {
          /* eslint-disable-next-line no-console */
          console.error('Error resolving request:', error);
          this.$toast.error(
            this.$t('working_schedule.resolve_error') ||
              'Failed to process request.',
          );
        })
        .finally(() => {
          this.isSubmitting = false;
        });
    },
  },
};
</script>

<style lang="scss" scoped>
.working-schedule-container {
  background-color: var(--oxd-background-color, #fff);
  border-radius: 0.5rem;
}

.working-schedule-loading {
  padding: 3rem;
  text-align: center;
  font-size: 1.1rem;
  color: var(--oxd-text-light-color, #777);
}

.working-schedule-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  margin-top: 1.5rem;
}

.schedule-card {
  display: flex;
  padding: 1.5rem;
  border-radius: 0.5rem;
  background-color: var(--oxd-background-pastel-white-color, #fdfdfd);
  border: 1px solid var(--oxd-input-control-border-color, #e0e0e0);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
  transition: all 0.3s ease;

  &:hover {
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
    transform: translateY(-2px);
  }
}

.card-header-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3.5rem;
  height: 3.5rem;
  margin-right: 1.25rem;
  border-radius: 50%;
  font-size: 1.5rem;
  background-color: rgba(var(--oxd-primary-one-color-rgb, 255, 123, 0), 0.1);
  color: var(--oxd-primary-one-color, #ff7b00);

  &.pending-icon {
    background-color: rgba(255, 193, 7, 0.1);
    color: #ffc107;
  }

  &.locked-icon {
    background-color: rgba(108, 117, 125, 0.1);
    color: #6c752d;
  }
}

.card-details {
  flex: 1;
}

.card-title {
  margin-top: 0;
  margin-bottom: 0.75rem;
  font-weight: 600;
  color: var(--oxd-text-color, #333);
}

.shift-info {
  .shift-name {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
    color: var(--oxd-primary-one-color, #ff7b00);
  }
  .shift-hours {
    font-size: 1rem;
    margin: 0.25rem 0 0 0;
    color: var(--oxd-text-light-color, #666);
  }
  .hours-badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 0.25rem;
    background-color: var(--oxd-background-color, #f0f0f0);
    margin-left: 0.5rem;
  }
}

.no-shift-info {
  color: var(--oxd-text-light-color, #888);
  font-style: italic;
}

.admin-config-card {
  background-color: rgba(var(--oxd-primary-one-color-rgb, 255, 123, 0), 0.02);
  border-left: 4px solid var(--oxd-primary-one-color, #ff7b00);
}

.switch-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 1rem 0;
  padding: 0.5rem 0;
  border-bottom: 1px dashed var(--oxd-input-control-border-color, #e0e0e0);
}

.switch-label {
  font-size: 1rem;
  font-weight: 500;
  margin: 0;
}

.config-help-text {
  font-size: 0.85rem;
  color: var(--oxd-text-light-color, #777);
  margin: 0;
}

.pending-request-card {
  border-left: 4px solid #ffc107;
  background-color: rgba(255, 193, 7, 0.02);
}

.pending-badge-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.status-badge {
  padding: 0.25rem 0.75rem;
  font-size: 0.85rem;
  font-weight: 600;
  border-radius: 1rem;

  &.--pending {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
  }
}

.request-details-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  margin-bottom: 1.25rem;

  @media (max-width: 768px) {
    grid-template-columns: 1fr;
  }
}

.request-detail-item {
  &.--full-width {
    grid-column: span 2;

    @media (max-width: 768px) {
      grid-column: span 1;
    }
  }

  .detail-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--oxd-text-light-color, #888);
    margin: 0 0 0.25rem 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .detail-value {
    font-size: 1rem;
    font-weight: 500;
    margin: 0;
    color: var(--oxd-text-color, #333);
  }

  .reason-text {
    background-color: var(--oxd-background-color, #f9f9f9);
    padding: 0.75rem;
    border-radius: 0.35rem;
    border: 1px solid var(--oxd-input-control-border-color, #eaeaea);
    font-style: italic;
    white-space: pre-line;
  }
}

.admin-resolve-actions {
  display: flex;
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid var(--oxd-input-control-border-color, #eaeaea);
}

.employee-pending-note {
  font-size: 0.9rem;
  font-style: italic;
  color: #856404;
  margin-top: 1rem;
}

.request-form-card {
  flex-direction: column;
  background-color: #fff;
}

.locked-schedule-card {
  border-left: 4px solid #6c757d;
  background-color: #f8f9fa;

  .locked-title {
    color: #495057;
  }

  .locked-desc {
    font-size: 0.95rem;
    color: #6c757d;
    margin: 0;
    line-height: 1.5;
  }
}
</style>
