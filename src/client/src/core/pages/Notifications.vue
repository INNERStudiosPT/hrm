<template>
  <div class="orangehrm-background-container">
    <div class="innerstudios-notifications">
      <div class="innerstudios-notifications-header">
        <oxd-text tag="h6" class="orangehrm-main-title">
          Notificações
        </oxd-text>
        <oxd-button
          display-type="secondary"
          label="Atualizar"
          :disabled="isLoading"
          @click="loadNotifications"
        />
      </div>
      <oxd-divider />

      <div v-if="isLoading" class="innerstudios-notifications-state">
        <oxd-loading-spinner />
      </div>

      <div v-else-if="error" class="innerstudios-notifications-state">
        <oxd-text tag="p" class="innerstudios-notifications-title">
          Não foi possível carregar as notificações.
        </oxd-text>
        <oxd-text tag="p" class="innerstudios-notifications-message">
          {{ error }}
        </oxd-text>
      </div>

      <div
        v-else-if="notifications.length === 0"
        class="innerstudios-notifications-state"
      >
        <oxd-text tag="p" class="innerstudios-notifications-title">
          Sem notificações
        </oxd-text>
      </div>

      <div v-else class="innerstudios-notifications-list">
        <a
          v-for="notification in notifications"
          :key="notification.id"
          class="innerstudios-notification"
          :class="{'--unread': !notification.read}"
          :href="notification.url || '#'"
          :target="notification.url ? '_blank' : null"
          rel="noreferrer"
          @click="onNotificationClick($event, notification)"
        >
          <span class="innerstudios-notification-dot"></span>
          <span class="innerstudios-notification-content">
            <oxd-text tag="p" class="innerstudios-notifications-title">
              {{ notification.title }}
            </oxd-text>
            <oxd-text tag="p" class="innerstudios-notifications-message">
              {{ notification.message }}
            </oxd-text>
            <oxd-text
              v-if="notification.createdAt"
              tag="p"
              class="innerstudios-notifications-date"
            >
              {{ notification.createdAt }}
            </oxd-text>
          </span>
        </a>
      </div>
    </div>
  </div>
</template>

<script>
import {APIService} from '@/core/util/services/api.service';
import {OxdSpinner} from '@ohrm/oxd';

export default {
  components: {
    'oxd-loading-spinner': OxdSpinner,
  },
  data() {
    return {
      isLoading: false,
      error: null,
      notifications: [],
    };
  },
  created() {
    this.http = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/innerstudios/notifications',
    );
    this.loadNotifications();
  },
  methods: {
    loadNotifications() {
      this.isLoading = true;
      this.error = null;
      this.http
        .getAll()
        .then((response) => {
          this.notifications = response.data?.data || [];
        })
        .catch((error) => {
          this.notifications = [];
          this.error =
            error?.response?.data?.error?.message ||
            'A API InnerStudios não devolveu notificações.';
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    onNotificationClick(event, notification) {
      if (!notification.url) {
        event.preventDefault();
      }
    },
  },
};
</script>

<style lang="scss" scoped>
.innerstudios-notifications {
  width: min(100%, 920px);
  margin: 0 auto;
  padding: 24px;
  background: $oxd-white-color;
  border-radius: 8px;
  box-shadow: 0 1px 8px rgba(100, 105, 120, 0.12);

  &-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }

  &-state {
    min-height: 180px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-align: center;
  }

  &-list {
    display: grid;
    gap: 8px;
  }

  &-title {
    font-weight: 700;
    color: $oxd-interface-gray-darken-1-color;
  }

  &-message,
  &-date {
    color: $oxd-interface-gray-color;
    font-size: 0.85rem;
  }
}

.innerstudios-notification {
  display: grid;
  grid-template-columns: 10px 1fr;
  gap: 12px;
  padding: 14px 0;
  text-decoration: none;
  border-bottom: 1px solid $oxd-interface-gray-lighten-2-color;

  &:last-child {
    border-bottom: 0;
  }

  &.--unread .innerstudios-notification-dot {
    background: $oxd-primary-one-color;
  }

  &-dot {
    width: 8px;
    height: 8px;
    margin-top: 8px;
    border-radius: 50%;
    background: $oxd-interface-gray-lighten-1-color;
  }

  &-content {
    display: grid;
    gap: 4px;
  }
}
</style>
