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
  <div v-if="isForcedBreakActive" class="orangehrm-forced-break-container">
    <div class="orangehrm-forced-break-title">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        style="
          width: 1.5rem;
          height: 1.5rem;
          display: inline-block;
          vertical-align: middle;
          margin-right: 0.25rem;
        "
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
        />
      </svg>
      Descanso Obrigatório de 1 Hora
    </div>
    <div class="orangehrm-forced-break-desc">
      Atingiu o limite de <strong>4 horas e 30 minutos</strong> de trabalho
      contínuo. Por motivos de segurança e saúde laboral, é obrigatório realizar
      uma pausa de 1 hora antes de iniciar o próximo turno.
    </div>

    <div class="orangehrm-timer-card">
      <div class="orangehrm-timer-text">
        {{ cooldownTimerString }}
      </div>
      <span class="orangehrm-timer-caption">
        Tempo restante até poder iniciar um novo turno
      </span>
    </div>
  </div>

  <oxd-form v-else :loading="isLoading" @submit-valid="onSave">
    <oxd-form-row>
      <oxd-grid :cols="4" class="orangehrm-full-width-grid">
        <template v-if="attendanceRecord.previousRecord">
          <oxd-grid-item
            :class="
              !attendanceRecord.previousRecord.note ? '--span-column-2' : ''
            "
          >
            <oxd-input-group :label="$t('attendance.punched_in_time')">
              <oxd-text type="subtitle-2">
                {{ previousAttendanceRecordDate }} -
                {{ previousAttendanceRecordTime }}
                <oxd-text
                  tag="span"
                  class="orangehrm-attendance-punchedIn-timezone"
                >
                  {{ `(GMT ${previousRecordTimezone})` }}
                </oxd-text>
              </oxd-text>
            </oxd-input-group>
          </oxd-grid-item>

          <oxd-grid-item v-if="attendanceRecord.previousRecord.note">
            <oxd-input-group :label="$t('attendance.punched_in_note')">
              <oxd-text type="subtitle-2">
                {{ attendanceRecord.previousRecord.note }}
              </oxd-text>
            </oxd-input-group>
          </oxd-grid-item>
        </template>

        <!-- Date Selector -->
        <oxd-grid-item class="--offset-row-2">
          <date-input
            :key="attendanceRecord.time"
            v-model="attendanceRecord.date"
            :label="$t('general.date')"
            :rules="rules.date"
            :disabled="!isEditable"
            required
          />
        </oxd-grid-item>

        <!-- Time  Selector -->
        <oxd-grid-item class="--offset-row-2">
          <oxd-input-field
            v-model="attendanceRecord.time"
            :label="$t('general.time')"
            :disabled="!isEditable"
            :rules="rules.time"
            type="time"
            :placeholder="$t('attendance.hh_mm')"
            required
          />
        </oxd-grid-item>
      </oxd-grid>
    </oxd-form-row>

    <!-- select timezone -->

    <oxd-grid v-if="isTimezoneEditable" :cols="2">
      <oxd-grid-item>
        <timezone-dropdown v-model="attendanceRecord.timezone" required />
      </oxd-grid-item>
    </oxd-grid>

    <!-- Note input -->
    <oxd-form-row>
      <oxd-grid :cols="4" class="orangehrm-full-width-grid">
        <oxd-grid-item class="--span-column-2">
          <oxd-input-field
            v-model="attendanceRecord.note"
            :rules="rules.note"
            :label="$t('general.note')"
            :placeholder="$t('general.type_here')"
            type="textarea"
          />
        </oxd-grid-item>
      </oxd-grid>
    </oxd-form-row>
    <oxd-divider />
    <oxd-form-actions>
      <required-text />
      <submit-button
        :label="
          !attendanceRecordId ? $t('attendance.in') : $t('attendance.out')
        "
      />
    </oxd-form-actions>
  </oxd-form>
</template>

<script>
import {
  required,
  validDateFormat,
  shouldNotExceedCharLength,
} from '@/core/util/validation/rules';
import {
  parseTime,
  parseDate,
  formatTime,
  formatDate,
  guessTimezone,
  setClockInterval,
  getStandardTimezone,
} from '@/core/util/helper/datefns';
import {promiseDebounce} from '@ohrm/oxd';
import useLocale from '@/core/util/composable/useLocale';
import {APIService} from '@ohrm/core/util/services/api.service';
import useDateFormat from '@/core/util/composable/useDateFormat';
import {reloadPage, navigate} from '@/core/util/helper/navigation';
import TimezoneDropdown from '@/orangehrmAttendancePlugin/components/TimezoneDropdown.vue';

const attendanceRecordModal = {
  date: null,
  time: null,
  note: null,
  timezone: null,
  previousRecord: null,
};

export default {
  name: 'RecordAttendance',
  components: {
    'timezone-dropdown': TimezoneDropdown,
  },
  props: {
    isEditable: {
      type: Boolean,
      default: false,
    },
    isTimezoneEditable: {
      type: Boolean,
      default: false,
    },
    attendanceRecordId: {
      type: Number,
      default: null,
    },
    employeeId: {
      type: Number,
      default: null,
    },
    date: {
      type: String,
      default: null,
    },
  },
  setup(props) {
    const apiPath = props.employeeId
      ? `/api/v2/attendance/employees/${props.employeeId}/records`
      : '/api/v2/attendance/records';
    const http = new APIService(window.appGlobal.baseUrl, apiPath);
    const {jsDateFormat, userDateFormat, timeFormat, jsTimeFormat} =
      useDateFormat();
    const {locale} = useLocale();
    return {
      http,
      locale,
      timeFormat,
      jsTimeFormat,
      jsDateFormat,
      userDateFormat,
    };
  },
  data() {
    return {
      isLoading: false,
      attendanceRecord: {...attendanceRecordModal},
      rules: {
        date: [
          required,
          validDateFormat(this.userDateFormat),
          promiseDebounce(this.validateDate, 500),
        ],
        time: [required, promiseDebounce(this.validateDate, 500)],
        note: [shouldNotExceedCharLength(250)],
      },
      previousRecordTimezone: null,
      latestRecord: null,
      isForcedBreakActive: false,
      cooldownEndTime: null,
      cooldownTimerString: '00:00:00',
      cooldownInterval: null,
    };
  },
  computed: {
    previousAttendanceRecordDate() {
      if (!this.attendanceRecord?.previousRecord) return null;
      return formatDate(
        parseDate(this.attendanceRecord.previousRecord.userDate),
        this.jsDateFormat,
        {locale: this.locale},
      );
    },
    previousAttendanceRecordTime() {
      if (!this.attendanceRecord?.previousRecord) return null;
      return formatTime(
        parseTime(
          this.attendanceRecord.previousRecord.userTime,
          this.timeFormat,
        ),
        this.jsTimeFormat,
      );
    },
  },
  beforeMount() {
    this.isLoading = true;
    // set default timezone
    if (this.isTimezoneEditable) {
      const tz = guessTimezone();
      this.attendanceRecord.timezone = {
        id: tz.name,
        label: tz.label,
        _name: tz.name,
        _offset: tz.offset,
      };
    }

    const justPunchedOut = sessionStorage.getItem('just_punched_out') === 'true';
    if (justPunchedOut) {
      sessionStorage.removeItem('just_punched_out');
    }

    // fetch and set attendance record on initial load
    this.setCurrentDateTime()
      .then(() => {
        // then set record date/time every minute
        !this.date &&
          !this.isEditable &&
          setClockInterval(this.setCurrentDateTime, 60000);
        let url = '/api/v2/attendance/records/latest';
        if (this.employeeId) {
          url = `/api/v2/attendance/records/latest?empNumber=${this.employeeId}`;
        }
        return this.http.request({method: 'GET', url});
      })

      .then((response) => {
        if (response && response.data && response.data.data) {
          const {data} = response.data;
          this.latestRecord = data;
          this.attendanceRecord.previousRecord = data.punchIn;

          this.checkForForcedBreak();
        }
      })
      .then(() => {
        this.previousRecordTimezone = getStandardTimezone(
          this.attendanceRecord.previousRecord?.offset,
        );
      })
      .then(() => {
        if (!this.attendanceRecordId && !this.isForcedBreakActive && !justPunchedOut) {
          this.onSave();
        }
      })
      .finally(() => {
        this.isLoading = false;
      });
  },
  beforeUnmount() {
    if (this.cooldownInterval) {
      clearInterval(this.cooldownInterval);
    }
  },
  methods: {
    checkForForcedBreak() {
      if (!this.latestRecord || !this.latestRecord.punchIn) return;

      const punchIn = this.latestRecord.punchIn;
      const punchOut = this.latestRecord.punchOut;

      const punchInTime = new Date(`${punchIn.utcDate}T${punchIn.utcTime}Z`);
      const now = new Date();

      if (!punchOut) {
        const elapsedMs = now.getTime() - punchInTime.getTime();
        const maxShiftMs = 4.5 * 60 * 60 * 1000;

        if (elapsedMs >= maxShiftMs) {
          this.isForcedBreakActive = true;
          this.cooldownEndTime = new Date(
            punchInTime.getTime() + maxShiftMs + 1 * 60 * 60 * 1000,
          );
          this.autoPunchOut(punchIn, maxShiftMs);
        } else {
          const remainingMs = maxShiftMs - elapsedMs;
          setTimeout(() => {
            this.checkForForcedBreak();
          }, remainingMs);
        }
      } else {
        const punchOutTime = new Date(
          `${punchOut.utcDate}T${punchOut.utcTime}Z`,
        );
        const shiftDurationMs = punchOutTime.getTime() - punchInTime.getTime();
        const limitMs = 4.5 * 60 * 60 * 1000 - 60000;

        if (shiftDurationMs >= limitMs) {
          const cooldownEnd = new Date(
            punchOutTime.getTime() + 1 * 60 * 60 * 1000,
          );
          if (now.getTime() < cooldownEnd.getTime()) {
            this.isForcedBreakActive = true;
            this.cooldownEndTime = cooldownEnd;
            this.startCooldownTimer();
          }
        }
      }
    },
    autoPunchOut(punchIn, maxShiftMs) {
      const punchInTime = new Date(`${punchIn.utcDate}T${punchIn.utcTime}Z`);
      const exactOutTime = new Date(punchInTime.getTime() + maxShiftMs);
      const timezone = guessTimezone();

      this.isLoading = true;
      this.http
        .request({
          method: 'PUT',
          data: {
            date: formatDate(exactOutTime, 'yyyy-MM-dd'),
            time: formatDate(exactOutTime, 'HH:mm'),
            note: 'Forçado a parar (limite de 4h30m atingido)',
            timezoneOffset: timezone.offset,
            timezoneName: timezone.name,
          },
        })
        .then(() => {
          this.isForcedBreakActive = true;
          this.cooldownEndTime = new Date(
            exactOutTime.getTime() + 1 * 60 * 60 * 1000,
          );
          this.startCooldownTimer();
        })
        .catch((err) => {
          console.error('[RecordAttendance] Forced punch out failed:', err); // eslint-disable-line no-console
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    startCooldownTimer() {
      if (this.cooldownInterval) {
        clearInterval(this.cooldownInterval);
      }

      const updateTimer = () => {
        const now = new Date().getTime();
        const distance = this.cooldownEndTime.getTime() - now;

        if (distance <= 0) {
          clearInterval(this.cooldownInterval);
          this.isForcedBreakActive = false;
          this.cooldownTimerString = '00:00:00';
          reloadPage();
          return;
        }

        const hours = Math.floor(
          (distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60),
        );
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        const pad = (num) => String(num).padStart(2, '0');
        this.cooldownTimerString = `${pad(hours)}:${pad(minutes)}:${pad(
          seconds,
        )}`;
      };

      updateTimer();
      this.cooldownInterval = setInterval(updateTimer, 1000);
    },
    onSave() {
      this.isLoading = true;

      const timezone = guessTimezone();

      this.http
        .request({
          method: this.attendanceRecordId ? 'PUT' : 'POST',
          data: {
            date: this.attendanceRecord.date,
            time: this.attendanceRecord.time,
            note: this.attendanceRecord.note,
            timezoneOffset:
              this.attendanceRecord.timezone?._offset ?? timezone.offset,
            timezoneName: this.attendanceRecord.timezone?.id ?? timezone.name,
          },
        })
        .then(() => {
          if (this.attendanceRecordId) {
            sessionStorage.setItem('just_punched_out', 'true');
          }
          return this.$toast.saveSuccess();
        })
        .then(() => {
          this.employeeId
            ? navigate('/attendance/viewAttendanceRecord', undefined, {
                employeeId: this.employeeId,
                date: this.date,
              })
            : reloadPage();
        })
        .catch(() => {
          this.isLoading = false;
        });
    },
    setCurrentDateTime() {
      return new Promise((resolve, reject) => {
        this.http
          .request({method: 'GET', url: '/api/v2/attendance/current-datetime'})
          .then((res) => {
            const {utcDate, utcTime} = res.data.data;
            const currentDate = parseDate(
              `${utcDate} ${utcTime} +00:00`,
              'yyyy-MM-dd HH:mm xxx',
            );
            this.attendanceRecord.date =
              this.date ?? formatDate(currentDate, 'yyyy-MM-dd');
            this.attendanceRecord.time = formatDate(currentDate, 'HH:mm');
            resolve();
          })
          .catch((error) => reject(error));
      });
    },
    validateDate() {
      if (!this.attendanceRecord.date || !this.attendanceRecord.time) {
        return true;
      }
      if (parseDate(this.attendanceRecord.date) === null) {
        return true;
      }
      const tzOffset = (new Date().getTimezoneOffset() / 60) * -1;
      return new Promise((resolve) => {
        this.http
          .request({
            method: 'GET',
            url: `/api/v2/attendance/${
              this.attendanceRecordId ? 'punch-out' : 'punch-in'
            }/overlaps`,
            params: {
              date: this.attendanceRecord.date,
              time: this.attendanceRecord.time,
              timezoneOffset:
                this.attendanceRecord.timezone?._offset ?? tzOffset,
              empNumber: this.employeeId,
            },
            // Prevent triggering response interceptor on 400
            validateStatus: (status) => {
              return (status >= 200 && status < 300) || status == 400;
            },
          })
          .then((res) => {
            const {data, error} = res.data;
            if (error) {
              return resolve(error.message);
            }
            return data.valid === true
              ? resolve(true)
              : resolve(this.$t('attendance.overlapping_records_found'));
          });
      });
    },
  },
};
</script>

<style src="./record-attendance.scss" lang="scss" scoped></style>

<style scoped>
.orangehrm-forced-break-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 2.5rem 1.5rem;
  border: 2px dashed #f59e0b;
  border-radius: 1rem;
  background-color: rgba(245, 158, 11, 0.05);
  margin: 1.5rem 0;
  font-family: 'Outfit', 'Inter', sans-serif;
}
.orangehrm-forced-break-title {
  color: #d97706;
  font-size: 1.25rem;
  font-weight: 700;
  margin-bottom: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.orangehrm-forced-break-desc {
  color: #6b7280;
  font-size: 0.875rem;
  line-height: 1.5;
  max-width: 28rem;
  margin-bottom: 1.5rem;
}
.orangehrm-timer-card {
  padding: 1.5rem 2.5rem;
  background-color: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 1rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  max-width: 24rem;
  margin: 0 auto;
}
.orangehrm-timer-text {
  font-family: monospace;
  font-size: 2.5rem;
  font-weight: 900;
  color: #d97706;
  letter-spacing: 0.1em;
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
.orangehrm-timer-caption {
  font-size: 0.75rem;
  color: #9ca3af;
  margin-top: 0.5rem;
  display: block;
}
@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.7;
  }
}
</style>
