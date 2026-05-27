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
      :title="$t('recruitment.hire_candidate')"
      @submit-valid="onSave"
    >
      <oxd-form-row>
        <oxd-grid :cols="3">
          <oxd-grid-item class="--span-column-2">
            <file-upload-input
              v-model:newFile="signedDocument"
              v-model:method="signedDocumentMethod"
              label="Documento assinado"
              button-label="Procurar"
              :rules="rules.signedDocument"
              url="innerstudios/hire-document"
              hint="Contrato assinado por ambas as partes."
              required
            />
            <a
              class="innerstudios-template-link"
              :href="templateDownloadUrl"
              target="_blank"
            >
              Descarregar template
            </a>
          </oxd-grid-item>
          <oxd-grid-item class="--span-column-2">
            <oxd-input-field
              v-model="note"
              :rules="rules.note"
              :label="$t('general.notes')"
              :placeholder="$t('general.type_here')"
              type="textarea"
            />
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
import {required, shouldNotExceedCharLength} from '@/core/util/validation/rules';
import FileUploadInput from '@/core/components/inputs/FileUploadInput';
import CandidateActionLayout from '@/orangehrmRecruitmentPlugin/components/CandidateActionLayout';
import {APIService} from '@/core/util/services/api.service';
import {navigate} from '@/core/util/helper/navigation';

export default {
  components: {
    'candidate-action-layout': CandidateActionLayout,
    'file-upload-input': FileUploadInput,
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
      `/api/v2/recruitment/candidates/${props.candidateId}/hire`,
    );

    return {
      http,
    };
  },
  data() {
    return {
      isLoading: false,
      note: null,
      signedDocument: null,
      signedDocumentMethod: 'replaceCurrent',
      templateDownloadUrl: `${window.appGlobal.baseUrl}/innerstudios/recruitment/hire-template`,
      rules: {
        signedDocument: [required],
        note: [shouldNotExceedCharLength(2000)],
      },
    };
  },
  methods: {
    onSave() {
      this.isLoading = true;
      this.http
        .request({
          method: 'PUT',
          data: {
            note: this.note,
            signedDocument: this.signedDocument,
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

<style lang="scss" scoped>
.innerstudios-template-link {
  display: inline-flex;
  margin-top: 8px;
  color: #ff7b1a;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
}
</style>
