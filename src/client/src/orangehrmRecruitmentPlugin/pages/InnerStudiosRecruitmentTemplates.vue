<template>
  <div class="orangehrm-background-container">
    <oxd-form :loading="isLoading" @submit-valid="onSave">
      <oxd-form-row>
        <oxd-grid :cols="3" class="orangehrm-full-width-grid">
          <oxd-grid-item class="--span-column-2">
            <file-upload-input
              v-model:newFile="template"
              v-model:method="method"
              label="Template de contratação"
              button-label="Procurar"
              :file="currentTemplate"
              :rules="rules.template"
              url="innerstudios/recruitment/hire-template"
              hint="Este ficheiro fica disponível no passo Hire."
              required
            />
          </oxd-grid-item>
        </oxd-grid>
      </oxd-form-row>
      <oxd-divider />
      <oxd-form-actions>
        <submit-button />
      </oxd-form-actions>
    </oxd-form>
  </div>
</template>

<script>
import {required} from '@/core/util/validation/rules';
import {APIService} from '@/core/util/services/api.service';
import FileUploadInput from '@/core/components/inputs/FileUploadInput';

export default {
  components: {
    'file-upload-input': FileUploadInput,
  },
  setup() {
    const http = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/innerstudios/recruitment/templates',
    );

    return {http};
  },
  data() {
    return {
      isLoading: false,
      template: null,
      currentTemplate: null,
      method: 'replaceCurrent',
      rules: {
        template: [required],
      },
    };
  },
  created() {
    this.isLoading = true;
    this.http
      .getAll()
      .then((response) => {
        const data = response.data?.data;
        if (data) {
          this.currentTemplate = {
            id: data.id,
            filename: data.file_name,
            fileType: data.file_type,
            fileSize: data.file_size,
          };
        }
      })
      .finally(() => {
        this.isLoading = false;
      });
  },
  methods: {
    onSave() {
      this.isLoading = true;
      this.http
        .create({template: this.template})
        .then(() => this.$toast.saveSuccess())
        .then(() => {
          window.location.reload();
        });
    },
  },
};
</script>
