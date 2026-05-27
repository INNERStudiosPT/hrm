<template>
  <oxd-layout v-bind="$attrs">
    <template v-for="(_, name) in $slots" #[name]="slotData">
      <slot :name="name" v-bind="slotData" />
    </template>
    <template #topbar-header-right-area>
      <div class="innerstudios-notifications" @click.stop>
        <button
          type="button"
          class="innerstudios-notifications-button"
          aria-label="Notificações"
          @click="toggleNotifications"
        >
          <oxd-icon name="bell-fill" />
          <span
            v-if="unreadNotificationsCount > 0"
            class="innerstudios-notifications-pill"
          >
            {{ unreadNotificationsLabel }}
          </span>
        </button>
        <div
          v-if="showNotifications"
          class="innerstudios-notifications-dropdown"
        >
          <div class="innerstudios-notifications-header">Notificações</div>
          <div
            v-if="notificationsLoading"
            class="innerstudios-notifications-empty"
          >
            A carregar...
          </div>
          <template v-else-if="notificationPreview.length > 0">
            <a
              v-for="notification in notificationPreview"
              :key="notification.id"
              class="innerstudios-notifications-item"
              :href="notification.url || notificationsUrl"
            >
              <span class="innerstudios-notifications-title">
                {{ notification.title }}
              </span>
              <span class="innerstudios-notifications-message">
                {{ notification.message }}
              </span>
            </a>
          </template>
          <div v-else class="innerstudios-notifications-empty">
            Sem notificações.
          </div>
          <a class="innerstudios-notifications-all" :href="notificationsUrl">
            Ver tudo
          </a>
        </div>
      </div>
    </template>
    <template #user-actions>
      <li>
        <a
          href="#"
          role="menuitem"
          class="oxd-userdropdown-link"
          @click="openAboutModel"
        >
          {{ $t('general.about') }}
        </a>
      </li>
      <li>
        <a :href="logoutUrl" role="menuitem" class="oxd-userdropdown-link">
          {{ $t('general.logout') }}
        </a>
      </li>
    </template>
  </oxd-layout>
  <about v-if="showAboutModel" @close="closeAboutModel"></about>
</template>

<script>
import {computed, onBeforeUnmount, onMounted, provide, readonly, ref} from 'vue';
import About from '@/core/pages/About.vue';
import {OxdIcon, OxdLayout} from '@ohrm/oxd';
import {dateFormatKey} from '@/core/util/composable/useDateFormat';
import {APIService} from '@/core/util/services/api.service';

export default {
  components: {
    about: About,
    'oxd-icon': OxdIcon,
    'oxd-layout': OxdLayout,
  },
  inheritAttrs: false,
  props: {
    permissions: {
      type: Object,
      default: () => ({}),
    },
    logoutUrl: {
      type: String,
      default: '#',
    },
    dateFormat: {
      type: Object,
      default: null,
    },
  },
  setup(props) {
    const showAboutModel = ref(false);
    const showNotifications = ref(false);
    const notifications = ref([]);
    const notificationsLoading = ref(false);
    const baseUrl = window.appGlobal?.baseUrl || '';
    const notificationsUrl = `${baseUrl}/innerstudios/notifications`;
    const notificationsHttp = new APIService(
      baseUrl,
      '/api/v2/innerstudios/notifications',
    );

    provide('permissions', readonly(props.permissions));
    provide(dateFormatKey, readonly(props.dateFormat));

    const openAboutModel = () => {
      showAboutModel.value = true;
    };

    const closeAboutModel = () => {
      showAboutModel.value = false;
    };

    const normalizeNotification = (item, index) => ({
      id: item?.id || index,
      title: item?.title || 'Notificação',
      message: item?.message || item?.title || '',
      url: item?.url || null,
      read: Boolean(item?.read),
    });

    const loadNotifications = async () => {
      notificationsLoading.value = true;
      try {
        const response = await notificationsHttp.getAll();
        const data = Array.isArray(response.data?.data) ? response.data.data : [];
        notifications.value = data.map(normalizeNotification);
      } catch (e) {
        notifications.value = [];
      } finally {
        notificationsLoading.value = false;
      }
    };

    const toggleNotifications = async () => {
      showNotifications.value = !showNotifications.value;
      if (showNotifications.value) {
        await loadNotifications();
      }
    };

    const closeNotifications = () => {
      showNotifications.value = false;
    };

    const unreadNotificationsCount = computed(() => {
      return notifications.value.filter((notification) => !notification.read).length;
    });

    const unreadNotificationsLabel = computed(() => {
      return unreadNotificationsCount.value > 99
        ? '99+'
        : `${unreadNotificationsCount.value}`;
    });

    const notificationPreview = computed(() => notifications.value.slice(0, 5));

    onMounted(() => {
      loadNotifications();
      window.addEventListener('click', closeNotifications);
    });

    onBeforeUnmount(() => {
      window.removeEventListener('click', closeNotifications);
    });

    return {
      showAboutModel,
      showNotifications,
      notificationsLoading,
      notificationsUrl,
      notificationPreview,
      unreadNotificationsCount,
      unreadNotificationsLabel,
      openAboutModel,
      closeAboutModel,
      toggleNotifications,
    };
  },
};
</script>

<style lang="scss" scoped>
.innerstudios-notifications {
  position: relative;
  display: flex;
  align-items: center;
  margin-right: 12px;
}

.innerstudios-notifications-button {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: 0;
  border-radius: 50%;
  color: #64728c;
  background: transparent;
  cursor: pointer;
}

.innerstudios-notifications-button:hover {
  color: #38455d;
  background: #f2f4f7;
}

.innerstudios-notifications-pill {
  position: absolute;
  top: 2px;
  right: 0;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 10px;
  color: #ffffff;
  background: #ff7b1a;
  font-size: 10px;
  font-weight: 700;
  line-height: 18px;
  text-align: center;
}

.innerstudios-notifications-dropdown {
  position: absolute;
  top: 44px;
  right: 0;
  z-index: 1000;
  width: min(340px, calc(100vw - 32px));
  overflow: hidden;
  border: 1px solid #e8eaef;
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 12px 28px rgba(38, 52, 79, 0.16);
}

.innerstudios-notifications-header {
  padding: 12px 16px;
  border-bottom: 1px solid #eef0f4;
  color: #38455d;
  font-size: 14px;
  font-weight: 700;
}

.innerstudios-notifications-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 16px;
  border-bottom: 1px solid #f3f4f6;
  text-decoration: none;
}

.innerstudios-notifications-item:hover {
  background: #f8fafc;
}

.innerstudios-notifications-title {
  color: #38455d;
  font-size: 13px;
  font-weight: 700;
}

.innerstudios-notifications-message,
.innerstudios-notifications-empty {
  color: #64728c;
  font-size: 12px;
  line-height: 1.45;
}

.innerstudios-notifications-empty {
  padding: 18px 16px;
}

.innerstudios-notifications-all {
  display: block;
  padding: 12px 16px;
  color: #ff7b1a;
  font-size: 13px;
  font-weight: 700;
  text-align: center;
  text-decoration: none;
}
</style>
