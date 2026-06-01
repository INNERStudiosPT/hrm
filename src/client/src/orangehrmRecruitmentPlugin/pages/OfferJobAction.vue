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
    <candidate-action-layout
      v-model:loading="isLoading"
      :candidate-id="candidateId"
      :title="$t('recruitment.offer_job')"
      @submit-valid="onSave"
    >
      <oxd-form-row>
        <oxd-grid :cols="3">
          <oxd-grid-item>
            <oxd-input-field
              v-model="offerType"
              type="select"
              label="Tipo de oferta"
              :options="offerTypeOptions"
              :rules="rules.offerType"
              required
            />
          </oxd-grid-item>
          <oxd-grid-item>
            <oxd-input-field
              v-model="workShift"
              type="select"
              label="Work shift"
              :options="workShiftOptions"
              :rules="rules.workShift"
              required
            />
          </oxd-grid-item>
          <oxd-grid-item>
            <oxd-input-field
              v-model="note"
              :rules="rules.note"
              :label="$t('general.notes')"
              :placeholder="$t('general.type_here')"
              type="textarea"
            />
          </oxd-grid-item>
          <oxd-grid-item v-if="offerType && offerType.id === 'contratacao'" class="--span-column-3">
            <div style="margin-top: 10px; padding: 12px; background-color: rgba(255, 123, 26, 0.08); border: 1px solid rgba(255, 123, 26, 0.2); border-radius: 6px;">
              <p style="margin: 0; font-size: 13px;">
                Link da Carta Oferta para preenchimento:
                <a href="http://docuseal.innerstudios.pt/d/st9HByMuczKDyG" target="_blank" style="color: #ff7b1a; font-weight: bold; text-decoration: underline; margin-left: 4px;">
                  http://docuseal.innerstudios.pt/d/st9HByMuczKDyG
                </a>
              </p>
            </div>
          </oxd-grid-item>
        </oxd-grid>
      </oxd-form-row>

      <oxd-divider />
      <oxd-form-actions>
        <oxd-button
          display-type="ghost"
          :label="$t('general.cancel')"
          @click="onClickBack"
        />
        <submit-button />
      </oxd-form-actions>
    </candidate-action-layout>
  </div>
</template>

<script>
import {
  required,
  shouldNotExceedCharLength,
} from '@/core/util/validation/rules';
import CandidateActionLayout from '@/orangehrmRecruitmentPlugin/components/CandidateActionLayout';
import {APIService} from '@/core/util/services/api.service';
import {navigate} from '@/core/util/helper/navigation';

export default {
  components: {
    'candidate-action-layout': CandidateActionLayout,
  },
  props: {
    candidateId: {
      type: Number,
      required: true,
    },
  },

  setup(props) {
    const http = new APIService(
      window.appGlobal.baseUrl,
      `/api/v2/recruitment/candidates/${props.candidateId}/job/offer`,
    );
    const httpWorkShifts = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/admin/work-shifts',
    );

    return {
      http,
      httpWorkShifts,
    };
  },
  data() {
    return {
      isLoading: false,
      note: null,
      workShift: null,
      offerType: { id: 'contratacao', label: 'Contratação' },
      offerTypeOptions: [
        { id: 'contratacao', label: 'Contratação' },
        { id: 'estagio', label: 'Estágio' },
      ],
      workShiftOptions: [
        {
          id: 'worker_decides',
          label: 'Decidido pelo trabalhador',
        },
      ],
      rules: {
        workShift: [required],
        offerType: [required],
        note: [shouldNotExceedCharLength(2000)],
      },
    };
  },
  created() {
    this.httpWorkShifts.getAll({limit: 0}).then((response) => {
      const data = Array.isArray(response.data?.data) ? response.data.data : [];
      this.workShiftOptions = [
        ...data.map((item) => ({
          id: item.id,
          label: item.name,
        })),
        {
          id: 'worker_decides',
          label: 'Decidido pelo trabalhador',
        },
      ];
    });
  },
  methods: {
    onSave() {
      this.isLoading = true;
      const workerDecides = this.workShift?.id === 'worker_decides';
      this.http
        .request({
          method: 'PUT',
          data: {
            note: this.note,
            workShiftId: workerDecides ? null : this.workShift?.id,
            workShiftWorkerDecides: workerDecides,
            offerType: this.offerType?.id || 'contratacao',
          },
        })
        .then(() => {
          return this.$toast.updateSuccess();
        })
        .then(() => {
          navigate('/recruitment/addCandidate/{id}', {id: this.candidateId});
        });
    },
    onClickBack() {
      navigate('/recruitment/addCandidate/{id}', {id: this.candidateId});
    },
  },
};
</script>
