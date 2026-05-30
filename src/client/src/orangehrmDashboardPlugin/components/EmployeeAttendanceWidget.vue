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
  <base-widget
    icon="clock-fill"
    :loading="isLoading"
    :title="$t('dashboard.time_at_work')"
  >
    <div class="orangehrm-attendance-card">
      <div class="orangehrm-attendance-card-profile">
        <div class="orangehrm-attendance-card-profile-image">
          <img
            alt="profile picture"
            class="employee-image"
            :src="`../pim/viewPhoto/empNumber/${empNumber}`"
          />
        </div>
        <div class="orangehrm-attendance-card-profile-record">
          <oxd-text tag="p" class="orangehrm-attendance-card-state">
            {{ lastState }}
          </oxd-text>
          <oxd-text tag="p" class="orangehrm-attendance-card-details">
            {{ lastRecord }}
          </oxd-text>
        </div>
      </div>
      <div class="orangehrm-attendance-card-bar">
        <oxd-text
          v-if="isForcedBreakActive"
          tag="span"
          class="orangehrm-attendance-card-fulltime"
          style="color: #d97706; font-weight: 700"
        >
          Pausa: <b>{{ cooldownTimerString }}</b> restantes
        </oxd-text>
        <oxd-text v-else tag="span" class="orangehrm-attendance-card-fulltime">
          <b>{{ dayTotal.hours }}h</b> <b>{{ dayTotal.minutes }}m</b>
          {{ $t('general.today') }}
        </oxd-text>
        <button
          :class="[
            'orangehrm-attendance-card-action-btn',
            isProcessing ? '--loading' : '',
            isForcedBreakActive ? '--cooldown' : '',
            state === 'PUNCHED IN' ? '--punched-in' : '',
          ]"
          :disabled="isProcessing || isForcedBreakActive"
          @click="onPunch"
        >
          <!-- Loading Micro-Spinner -->
          <svg v-if="isProcessing" class="spinner" viewBox="0 0 50 50">
            <circle
              class="path"
              cx="25"
              cy="25"
              r="20"
              fill="none"
              stroke-width="5"
            ></circle>
          </svg>
          <!-- Cooldown Pause Icon -->
          <oxd-icon v-else-if="isForcedBreakActive" name="pause-fill" />
          <!-- Normal Stopwatch / Exit -->
          <oxd-icon v-else name="stopwatch" />
        </button>
      </div>
      <oxd-divider />
      <div class="orangehrm-attendance-card-summary">
        <div class="orangehrm-attendance-card-summary-week">
          <oxd-text tag="p">
            {{ $t('dashboard.this_week') }}
          </oxd-text>
          <oxd-text tag="p">
            {{ currentWeek }}
          </oxd-text>
        </div>
        <div class="orangehrm-attendance-card-summary-total">
          <oxd-icon name="stopwatch" class="orangehrm-attendance-card-icon" />
          <oxd-text tag="p" class="orangehrm-attendance-card-fulltime">
            {{ weekTotal.hours }}h {{ weekTotal.minutes }}m
          </oxd-text>
        </div>
      </div>
    </div>
    <oxd-bar-chart
      :grid="false"
      :data="dataset"
      :y-axsis="false"
      :aspect-ratio="false"
      wrapper-classes="emp-attendance-chart"
    ></oxd-bar-chart>

    <!-- Premium Glassmorphism Forced Break Modal -->
    <transition name="modal-fade">
      <div v-if="showForcedBreakModal" class="orangehrm-modal-overlay">
        <div class="orangehrm-modal-card">
          <div class="orangehrm-modal-header">
            <div class="orangehrm-modal-icon-container">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="orangehrm-modal-icon"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2.5"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
              </svg>
            </div>
            <h3>Pausa de Almoço Obrigatória</h3>
          </div>
          <div class="orangehrm-modal-body">
            <p>
              Atingiu o limite de <strong>4 horas e 30 minutos</strong> de
              trabalho contínuo.
            </p>
            <p>
              Por motivos de segurança e saúde laboral, o seu turno foi
              interrompido para o descanso obrigatório de 1 hora.
            </p>
            <div class="orangehrm-modal-timer">
              <div class="timer-value">{{ cooldownTimerString }}</div>
              <div class="timer-label">Tempo restante até poder voltar</div>
            </div>
          </div>
          <div class="orangehrm-modal-footer">
            <button
              class="orangehrm-modal-btn"
              @click="showForcedBreakModal = false"
            >
              Compreendido
            </button>
          </div>
        </div>
      </div>
    </transition>
  </base-widget>
</template>

<script>
import {
  isToday,
  freshDate,
  parseDate,
  formatDate,
  guessTimezone,
} from '@/core/util/helper/datefns';
import useLocale from '@/core/util/composable/useLocale';
import {APIService} from '@/core/util/services/api.service';
import BaseWidget from '@/orangehrmDashboardPlugin/components/BaseWidget.vue';
import {OxdBarChart, OxdIcon, CHART_COLORS} from '@ohrm/oxd';

export default {
  name: 'EmployeeAttendanceWidget',

  components: {
    'oxd-icon': OxdIcon,
    'base-widget': BaseWidget,
    'oxd-bar-chart': OxdBarChart,
  },

  setup() {
    const {locale} = useLocale();
    const http = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/dashboard/employees/time-at-work',
    );
    const attendanceHttp = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/attendance/records',
    );

    return {
      http,
      attendanceHttp,
      locale,
    };
  },

  data() {
    return {
      dataset: [],
      state: null,
      endDate: null,
      userDate: null,
      userTime: null,
      startDate: null,
      isLoading: false,
      isProcessing: false,
      empNumber: null,
      timezoneOffset: null,
      dayTotal: {
        hours: 0,
        minutes: 0,
      },
      weekTotal: {
        hours: 0,
        minutes: 0,
      },
      isForcedBreakActive: false,
      showForcedBreakModal: false,
      cooldownEndTime: null,
      cooldownTimerString: '00:00:00',
      cooldownInterval: null,
      realtimeCheckInterval: null,
      latestRecord: null,
    };
  },

  computed: {
    lastState() {
      switch (this.state) {
        case 'PUNCHED IN':
          return this.$t('attendance.punched_in');
        case 'PUNCHED OUT':
          return this.$t('attendance.punched_out');
        default:
          return this.$t('attendance.not_punched_in');
      }
    },
    lastRecord() {
      if (!this.userDate || !this.userTime) return null;
      const parsedDate = parseDate(
        `${this.userDate} ${this.userTime}`,
        'yyyy-MM-dd HH:mm',
      );
      const formattedTime = formatDate(parsedDate, 'hh:mm a', {
        locale: this.locale,
      });

      if (!isToday(parsedDate)) {
        const formattedDate = formatDate(parsedDate, 'MMM do', {
          locale: this.locale,
        });
        return this.$t('dashboard.state_date_at_time_timezone_offset', {
          lastState: this.lastState,
          date: formattedDate,
          time: formattedTime,
          timezoneOffset: this.timezoneOffset,
        });
      }

      return this.$t('dashboard.state_today_at_time_timezone_offset', {
        lastState: this.lastState,
        time: formattedTime,
        timezoneOffset: this.timezoneOffset,
      });
    },
    currentWeek() {
      if (!this.startDate || !this.endDate) return null;
      const startDate = formatDate(parseDate(this.startDate), 'MMM dd', {
        locale: this.locale,
      });
      const endDate = formatDate(parseDate(this.endDate), 'MMM dd', {
        locale: this.locale,
      });
      return `${startDate} - ${endDate}`;
    },
  },

  beforeMount() {
    this.fetchWidgetData();
  },

  beforeUnmount() {
    if (this.cooldownInterval) {
      clearInterval(this.cooldownInterval);
    }
    if (this.realtimeCheckInterval) {
      clearInterval(this.realtimeCheckInterval);
    }
  },

  methods: {
    onPunch() {
      if (this.isProcessing || this.isForcedBreakActive) return;

      this.isProcessing = true;

      // Fetch the safe server current datetime before performing the punch
      this.attendanceHttp
        .request({method: 'GET', url: '/current-datetime'})
        .then((res) => {
          const {utcDate, utcTime} = res.data.data;
          const currentDate = parseDate(
            `${utcDate} ${utcTime} +00:00`,
            'yyyy-MM-dd HH:mm xxx',
          );
          const finalDate = formatDate(currentDate, 'yyyy-MM-dd');
          const finalTime = formatDate(currentDate, 'HH:mm');

          const timezone = guessTimezone();
          const isPunchedIn = this.state === 'PUNCHED IN';

          return this.attendanceHttp.request({
            method: isPunchedIn ? 'PUT' : 'POST',
            data: {
              date: finalDate,
              time: finalTime,
              note: '',
              timezoneOffset: timezone.offset,
              timezoneName: timezone.name,
            },
          });
        })
        .then(() => {
          this.$toast.saveSuccess();
          this.fetchWidgetData();
        })
        .catch((err) => {
          /* eslint-disable-next-line no-console */
          console.error(
            '[EmployeeAttendanceWidget] Punch operation failed:',
            err,
          );
        })
        .finally(() => {
          this.isProcessing = false;
        });
    },

    fetchWidgetData() {
      this.isLoading = true;
      const currentDate = freshDate();
      const timezoneOffset = (currentDate.getTimezoneOffset() / 60) * -1;

      const fetchLatest = this.attendanceHttp
        .request({method: 'GET', url: '/latest'})
        .then((res) => {
          if (res && res.data && res.data.data) {
            this.latestRecord = res.data.data;
            this.checkForForcedBreak();
          }
        })
        .catch((err) => {
          /* eslint-disable-next-line no-console */
          console.error(
            '[EmployeeAttendanceWidget] Failed to fetch latest record:',
            err,
          );
        });

      const fetchWidget = this.http
        .getAll({
          timezoneOffset,
          currentDate: formatDate(currentDate, 'yyyy-MM-dd'),
          currentTime: formatDate(new Date(), 'HH:mm'),
        })
        .then((response) => {
          const {data, meta} = response.data;
          this.dataset = data.map((item) => ({
            value: item.totalTime.hours + item.totalTime.minutes / 60,
            label: this.$t(
              `general.${new String(item.workDay.day).toLowerCase()}`,
            ),
            color: CHART_COLORS.COLOR_HEAT_WAVE,
          }));

          const {lastAction, currentDay, currentWeek, currentUser} = meta;
          if (lastAction) {
            this.state = lastAction.state;
            this.userDate = lastAction.userDate;
            this.userTime = lastAction.userTime;
            this.timezoneOffset = lastAction.timezoneOffset;
          }
          if (currentWeek) {
            this.weekTotal = currentWeek.totalTime;
            this.endDate = currentWeek.endDate?.date;
            this.startDate = currentWeek.startDate?.date;
          }
          if (currentDay) {
            this.dayTotal = currentDay.totalTime;
          }
          if (currentUser) {
            this.empNumber = currentUser.empNumber;
          }
        })
        .catch((err) => {
          /* eslint-disable-next-line no-console */
          console.error(
            '[EmployeeAttendanceWidget] Failed to fetch widget metadata:',
            err,
          );
        });

      Promise.all([fetchLatest, fetchWidget]).finally(() => {
        this.isLoading = false;
      });
    },

    checkForForcedBreak() {
      if (!this.latestRecord || !this.latestRecord.punchIn) {
        if (this.realtimeCheckInterval) {
          clearInterval(this.realtimeCheckInterval);
        }
        this.isForcedBreakActive = false;
        return;
      }

      const punchIn = this.latestRecord.punchIn;
      const punchOut = this.latestRecord.punchOut;

      const punchInTime = new Date(`${punchIn.utcDate}T${punchIn.utcTime}Z`);
      const now = new Date();

      if (!punchOut) {
        // Punched in - start real-time monitoring ticker!
        if (this.realtimeCheckInterval) {
          clearInterval(this.realtimeCheckInterval);
        }

        const maxShiftMs = 4.5 * 60 * 60 * 1000;

        const checkTicker = () => {
          const currentNow = new Date();
          const elapsedMs = currentNow.getTime() - punchInTime.getTime();

          if (elapsedMs >= maxShiftMs) {
            clearInterval(this.realtimeCheckInterval);
            this.triggerForcedBreak(punchIn, maxShiftMs);
          }
        };

        // Run checking ticker every second
        checkTicker();
        this.realtimeCheckInterval = setInterval(checkTicker, 1000);
      } else {
        // Punched out - check if we are in the 1-hour cooldown period!
        if (this.realtimeCheckInterval) {
          clearInterval(this.realtimeCheckInterval);
        }

        const punchOutTime = new Date(
          `${punchOut.utcDate}T${punchOut.utcTime}Z`,
        );
        const shiftDurationMs = punchOutTime.getTime() - punchInTime.getTime();
        const limitMs = 4.5 * 60 * 60 * 1000 - 60000; // 4.5 hours minus 1 min tolerance

        if (shiftDurationMs >= limitMs) {
          const cooldownEnd = new Date(
            punchOutTime.getTime() + 1 * 60 * 60 * 1000,
          );
          if (now.getTime() < cooldownEnd.getTime()) {
            this.isForcedBreakActive = true;
            this.cooldownEndTime = cooldownEnd;
            this.startCooldownTimer();
          } else {
            this.isForcedBreakActive = false;
          }
        } else {
          this.isForcedBreakActive = false;
        }
      }
    },

    triggerForcedBreak(punchIn, maxShiftMs) {
      // 1. Play synthesized premium chime sound!
      this.playAlertSound();

      // 2. Open warning modal
      this.showForcedBreakModal = true;
      this.isForcedBreakActive = true;

      // 3. Compute exact cooldown end time
      const punchInTime = new Date(`${punchIn.utcDate}T${punchIn.utcTime}Z`);
      const exactOutTime = new Date(punchInTime.getTime() + maxShiftMs);
      this.cooldownEndTime = new Date(
        exactOutTime.getTime() + 1 * 60 * 60 * 1000,
      );

      // Start counting down immediately
      this.startCooldownTimer();

      // 4. Send PUT request to force-out via the API!
      const timezone = guessTimezone();
      this.isProcessing = true;

      this.attendanceHttp
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
          this.$toast.saveSuccess();
          this.fetchWidgetData();
        })
        .catch((err) => {
          /* eslint-disable-next-line no-console */
          console.error(
            '[EmployeeAttendanceWidget] Forced punch out failed:',
            err,
          );
        })
        .finally(() => {
          this.isProcessing = false;
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
          this.showForcedBreakModal = false;
          this.cooldownTimerString = '00:00:00';
          this.fetchWidgetData();
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

    playAlertSound() {
      try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();

        const playTone = (freq, start, duration) => {
          const osc = ctx.createOscillator();
          const gain = ctx.createGain();
          osc.type = 'sine';
          osc.frequency.setValueAtTime(freq, start);

          gain.gain.setValueAtTime(0.15, start);
          gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);

          osc.connect(gain);
          gain.connect(ctx.destination);
          osc.start(start);
          osc.stop(start + duration);
        };

        // Premium ascending alarm chime
        playTone(523.25, ctx.currentTime, 0.4); // C5
        playTone(659.25, ctx.currentTime + 0.15, 0.4); // E5
        playTone(783.99, ctx.currentTime + 0.3, 0.6); // G5
      } catch (e) {
        /* eslint-disable-next-line no-console */
        console.error('Failed to play synthesized alert sound:', e);
      }
    },
  },
};
</script>

<style src="./employee-attendance-widget.scss" lang="scss" scoped></style>
