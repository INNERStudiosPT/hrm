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
  <oxd-sheet :gutters="false" type="white" class="orangehrm-buzz">
    <div class="orangehrm-buzz-post">
      <div class="orangehrm-buzz-post-header">
        <div class="orangehrm-buzz-post-header-details">
          <profile-image :employee="post.employee"></profile-image>
          <div class="orangehrm-buzz-post-header-text">
            <oxd-text tag="p" class="orangehrm-buzz-post-emp-name">
              {{ employeeFullName }}
              <span
                v-if="awardWeek"
                class="buzz-award-badge buzz-award-week"
                title="Colaborador da Semana"
              >⭐ Semana</span>
              <span
                v-if="awardMonth"
                class="buzz-award-badge buzz-award-month"
                title="Colaborador do Mês"
              >🏆 Mês</span>
            </oxd-text>
            <oxd-text tag="p" class="orangehrm-buzz-post-time">
              {{ postDateTime }}
            </oxd-text>
          </div>
        </div>
        <div
          v-if="post.permission.canUpdate || post.permission.canDelete"
          class="orangehrm-buzz-post-header-config"
        >
          <oxd-dropdown>
            <oxd-icon-button name="three-dots" :with-container="true" />
            <template #content>
              <li
                v-if="post.permission.canDelete"
                class="orangehrm-buzz-post-header-config-item"
                @click="$emit('delete', $event)"
              >
                <oxd-icon name="trash" />
                <oxd-text tag="p">
                  {{ $t('buzz.delete_post') }}
                </oxd-text>
              </li>
              <li
                v-if="post.permission.canUpdate"
                class="orangehrm-buzz-post-header-config-item"
                @click="$emit('edit', $event)"
              >
                <oxd-icon name="pencil" />
                <oxd-text tag="p">
                  {{ $t('buzz.edit_post') }}
                </oxd-text>
              </li>
            </template>
          </oxd-dropdown>
        </div>
      </div>
      <oxd-divider />
    </div>
    <div class="orangehrm-buzz-post-body">
      <slot name="content"></slot>
      <slot name="body"></slot>
    </div>
    <div class="orangehrm-buzz-post-footer">
      <slot name="actionButton"></slot>
      <slot name="postStats"></slot>
    </div>
    <slot name="comments"></slot>
  </oxd-sheet>
</template>

<script>
import {computed} from 'vue';
import useLocale from '@/core/util/composable/useLocale';
import useDateFormat from '@/core/util/composable/useDateFormat';
import {formatDate, parseDate} from '@/core/util/helper/datefns';
import ProfileImage from '@/orangehrmBuzzPlugin/components/ProfileImage';
import useEmployeeNameTranslate from '@/core/util/composable/useEmployeeNameTranslate';
import {OxdDropdownMenu, OxdIcon, OxdSheet} from '@ohrm/oxd';

export default {
  name: 'PostContainer',
  components: {
    'oxd-icon': OxdIcon,
    'oxd-sheet': OxdSheet,
    'oxd-dropdown': OxdDropdownMenu,
    'profile-image': ProfileImage,
  },
  props: {
    post: {
      type: Object,
      required: true,
    },
    awardWeek: {
      type: Boolean,
      default: false,
    },
    awardMonth: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['edit', 'delete'],

  setup(props) {
    const {locale} = useLocale();
    const {jsDateFormat, jsTimeFormat} = useDateFormat();
    const {$tEmpName} = useEmployeeNameTranslate();

    const employeeFullName = computed(() => {
      return $tEmpName(props.post.employee, {
        includeMiddle: true,
        excludePastEmpTag: false,
      });
    });

    const postDateTime = computed(() => {
      const {createdDate, createdTime} = props.post;

      const utcDate = parseDate(
        `${createdDate} ${createdTime} +00:00`,
        'yyyy-MM-dd HH:mm xxx',
      );

      return formatDate(utcDate, `${jsDateFormat} ${jsTimeFormat}`, {
        locale,
      });
    });

    return {
      postDateTime,
      employeeFullName,
    };
  },
};
</script>

<style lang="scss" scoped src="./post-container.scss"></style>

<style lang="scss" scoped>
.buzz-award-badge {
  display: inline-flex;
  align-items: center;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  margin-left: 6px;
  vertical-align: middle;
  letter-spacing: 0.03em;
  cursor: default;
  white-space: nowrap;
  transition: transform 0.15s ease, box-shadow 0.15s ease;

  &:hover {
    transform: scale(1.08);
  }
}

.buzz-award-week {
  background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
  color: #1a1a1a;
  box-shadow: 0 0 8px rgba(245, 158, 11, 0.55);
}

.buzz-award-month {
  background: linear-gradient(135deg, #6d28d9 0%, #8b5cf6 100%);
  color: #fff;
  box-shadow: 0 0 8px rgba(109, 40, 217, 0.55);
}
</style>
