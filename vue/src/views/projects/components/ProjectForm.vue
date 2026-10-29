<template>
  <Modal :title="$t('projects.form.title.' + type)" :show="isShown" @close="$emit('close')">
    <template #content>
      <v-form @submit.prevent="submitForm" id="project-form" class="Form Form--project">
        <NewSubmission
          v-if="type === FormType.VALIDATE && project"
          :created-by="project.createdBy"
          :created-at="project.createdAt"
          :message="project.creatorMessage"
        />
        <div class="Form__fieldCtn">
          <label class="Form__label required">{{ $t('projects.form.fields.name.label') }}</label>
          <v-text-field
            density="compact"
            variant="outlined"
            v-model="form.name.value.value"
            :error-messages="form.name.errorMessage.value"
            :placeholder="$t('projects.form.fields.name.label')"
            @blur="form.name.handleChange"
          />
        </div>
        <div class="Form__fieldCtn">
          <label class="Form__label required">{{
            $t('projects.form.fields.description.label')
          }}</label>
          <TextEditor
            v-model:content-model="form.description.value.value"
            :parent-form-error="formError"
          />
        </div>
        <div class="Form__fieldCtn">
          <label class="Form__label">{{ $t('actors.form.logo') }}</label>
          <ImagesLoader
            @updateFiles="handleLogoUpdate"
            :existingImages="existingLogo"
            :uniqueImage="true"
            :externalImagesLoader="false"
          />
        </div>
        <div class="Form__fieldCtn">
          <label class="Form__label required">{{ $t('projects.form.fields.status.label') }}</label>
          <v-select
            density="compact"
            variant="outlined"
            chips
            v-model="form.status.value.value as Status"
            :items="Object.values(Status)"
            :placeholder="$t('projects.form.fields.status.label')"
            :item-title="(item) => $t('projects.status.' + item)"
            :item-value="(item) => item"
            :error-messages="form.status.errorMessage.value"
            @blur="form.status.handleChange(form.status.value.value)"
          />
        </div>
        <div class="Form__fieldCtn" v-if="(form.status.value.value as Status) === Status.PLANNED">
          <label class="Form__label">{{ $t('projects.form.fields.technicalMaturity.label') }}</label>
          <v-select
            density="compact"
            variant="outlined"
            clearable
            v-model="form.technicalMaturity.value.value as TechnicalMaturity"
            :items="Object.values(TechnicalMaturity)"
            :placeholder="$t('projects.form.fields.technicalMaturity.label')"
            :item-title="(item) => $t('projects.technicalMaturity.' + item)"
            :item-value="(item) => item"
            :error-messages="form.technicalMaturity.errorMessage.value"
            @blur="form.technicalMaturity.handleChange(form.technicalMaturity.value.value)"
          />
        </div>

        <template v-if="showFinancialFields">
          <div class="Form__fieldCtn">
            <label class="Form__label">{{ $t('projects.form.fields.financialStatus.label') }}</label>
            <v-select
              density="compact"
              variant="outlined"
              clearable
              v-model="form.financialStatus.value.value as FinancialStatus"
              :items="Object.values(FinancialStatus)"
              :placeholder="$t('projects.form.fields.financialStatus.label')"
              :item-title="(item) => $t('projects.financialStatus.' + item)"
              :item-value="(item) => item"
              :error-messages="form.financialStatus.errorMessage.value"
              @blur="form.financialStatus.handleChange(form.financialStatus.value.value)"
            />
          </div>

          <div class="d-flex">
            <div class="Form__fieldCtn flex-grow-1">
              <label class="Form__label">{{ $t('projects.form.fields.totalBudget.label') }}</label>
              <v-text-field
                density="compact"
                variant="outlined"
                type="number"
                min="0"
                suffix="FCFA"
                v-model="form.totalBudget.value.value"
                :placeholder="$t('projects.form.fields.totalBudget.label')"
                :error-messages="form.totalBudget.errorMessage.value"
                @blur="form.totalBudget.handleChange(form.totalBudget.value.value)"
              />
            </div>
            <div class="Form__fieldCtn ml-3 flex-grow-1">
              <label class="Form__label">{{ $t('projects.form.fields.mobilizedFunds.label') }}</label>
              <v-text-field
                density="compact"
                variant="outlined"
                type="number"
                min="0"
                suffix="FCFA"
                v-model="form.mobilizedFunds.value.value"
                :placeholder="$t('projects.form.fields.mobilizedFunds.label')"
                :error-messages="form.mobilizedFunds.errorMessage.value"
                @blur="form.mobilizedFunds.handleChange(form.mobilizedFunds.value.value)"
              />
            </div>
          </div>

          <div class="Form__fieldCtn" v-if="residualGap !== null">
            <label class="Form__label">{{ $t('projects.form.fields.residualGap.label') }}</label>
            <v-text-field
              density="compact"
              variant="outlined"
              suffix="FCFA"
              :model-value="residualGap"
              readonly
              disabled
            />
          </div>
        </template>
        <div class="Form__fieldCtn">
          <label class="Form__label">{{ $t('projects.form.fields.deliverables.label') }}</label>
          <TextEditor
            v-model:content-model="form.deliverables.value.value"
            :parent-form-error="formError"
            :min-length="0"
          />
        </div>
        <div class="Form__fieldCtn">
          <label class="Form__label">{{ $t('projects.form.fields.calendar.label') }}</label>
          <TextEditor
            v-model:content-model="form.calendar.value.value"
            :parent-form-error="formError"
            :min-length="0"
          />
        </div>
        <div class="Form__fieldCtn">
          <label class="Form__label">{{ $t('projects.form.fields.website.label') }}</label>
          <v-text-field
            density="compact"
            variant="outlined"
            v-model="form.website.value.value"
            :placeholder="$t('projects.form.fields.website.label')"
            :error-messages="form.website.errorMessage.value"
            @blur="form.website.handleChange"
          />
        </div>

        

        <v-divider color="main-grey" class="border-opacity-100"></v-divider>
        <FormSectionTitle :text="$t('projects.form.section.adminScope')" />
        <div class="Form__fieldCtn">
          <label class="Form__label required">{{ $t('actors.form.adminScope') }}</label>
          <v-select
            density="compact"
            variant="outlined"
            multiple
            v-model="form.administrativeScopes.value.value as AdministrativeScope[]"
            :items="Object.values(AdministrativeScope)"
            :item-title="(item) => $t('actors.scope.' + item)"
            :item-value="(item) => item"
            :error-messages="form.administrativeScopes.errorMessage.value"
            @blur="form.administrativeScopes.handleChange(form.administrativeScopes.value.value)"
          />
        </div>
        <div class="Form__fieldCtn" v-if="activeAdminLevels && activeAdminLevels.admin1">
          <label class="Form__label">{{ $t('actors.form.admin1') }}</label>
          <v-autocomplete
            multiple
            density="compact"
            :items="adminBoundariesStore.admin1Boundaries"
            item-title="adm1Name"
            item-value="@id"
            variant="outlined"
            v-model="form.admin1List.value.value as Admin1Boundary[]"
            return-object
          ></v-autocomplete>
        </div>
        <div class="Form__fieldCtn" v-if="activeAdminLevels && activeAdminLevels.admin3">
          <label class="Form__label">{{ $t('actors.form.admin3') }}</label>
          <v-autocomplete
            multiple
            density="compact"
            :items="adminBoundariesStore.admin3Boundaries"
            item-title="adm3Name"
            item-value="@id"
            variant="outlined"
            v-model="form.admin3List.value.value as Admin3Boundary[]"
            return-object
          ></v-autocomplete>
        </div>

        <label class="Form__label">{{ $t('forms.banoc.title') }}</label>
        <div class="d-flex">
          <div class="Form__fieldCtn flex-shrink-0">
            <label class="Form__label">{{ $t('forms.banoc.code') }}</label>
            <div>
              <v-text-field
                density="compact"
                variant="outlined"
                v-model="form.banoc.value.value"
                :error-messages="form.banoc.errorMessage.value"
                @blur="form.banoc.handleChange"
              />
            </div>
          </div>
          <div class="Form__fieldCtn ml-3 flex-grow-1">
            <label class="Form__label">{{ $t('forms.banoc.url') }}</label>
            <div>
              <v-text-field
                density="compact"
                variant="outlined"
                v-model="form.banocUrl.value.value"
                :error-messages="form.banocUrl.errorMessage.value"
                @blur="form.banocUrl.handleChange"
              />
            </div>
          </div>
        </div>

        <v-divider color="main-grey" class="border-opacity-100"></v-divider>
        <FormSectionTitle :text="$t('projects.form.section.localization')" />
        <LocationSelector
          @update:model-value="form.geoData.handleChange"
          v-model="form.geoData.value.value as GeoData"
          :error-message="form.geoData.errorMessage.value"
        />

        <FormSectionTitle :text="$t('projects.form.section.thematics')" />
        <v-select
          density="compact"
          variant="outlined"
          multiple
          v-model="form.thematics.value.value as Thematic[]"
          :items="Object.values(Thematic)"
          :item-title="(item) => $t('forms.thematics.' + item)"
          :item-value="(item) => item"
          :placeholder="$t('projects.form.section.thematics')"
          :error-messages="form.thematics.errorMessage.value"
          @blur="form.thematics.handleChange(form.thematics.value.value)"
          return-object
        />
        <div class="Form__fieldCtn" v-if="otherThematicIsSelected">
          <label class="Form__label conditionnal">{{
            $t('projects.form.section.otherThematic')
          }}</label>
          <v-text-field
            density="compact"
            variant="outlined"
            v-model="form.otherThematic.value.value"
            :error-messages="form.otherThematic.errorMessage.value"
            @blur="form.otherThematic.handleChange"
          />
        </div>

        <div class="Form__fieldCtn">
          <label class="Form__label required">{{ $t('forms.odds.title') }}</label>
          <v-select
            density="compact"
            variant="outlined"
            multiple
            v-model="form.odds.value.value as ODD[]"
            :items="Object.values(ODD)"
            :item-title="(item) => $t('forms.odds.' + item)"
            :item-value="(item) => item"
            :error-messages="form.odds.errorMessage.value"
            @blur="form.odds.handleChange(form.odds.value.value)"
            return-object
          />
        </div>

        <FormSectionTitle :text="$t('projects.form.section.beneficiaryTypes')" />
        <v-autocomplete
          density="compact"
          variant="outlined"
          multiple
          v-model="form.beneficiaryTypes.value.value as BeneficiaryType[]"
          :items="Object.values(BeneficiaryType)"
          :placeholder="$t('projects.form.section.beneficiaryTypes')"
          :item-title="(item) => $t('beneficiaryType.' + item)"
          :item-value="(item) => item"
          :error-messages="form.beneficiaryTypes.errorMessage.value"
          @blur="form.beneficiaryTypes.handleChange(form.beneficiaryTypes.value.value)"
          return-object
        />
        <div class="Form__fieldCtn" v-if="otherBeneficiaryIsSelected">
          <label class="Form__label conditionnal">{{
            $t('projects.form.section.otherBeneficiary')
          }}</label>
          <v-text-field
            density="compact"
            variant="outlined"
            v-model="form.otherBeneficiary.value.value"
            :error-messages="form.otherBeneficiary.errorMessage.value"
            @blur="form.otherBeneficiary.handleChange"
          />
        </div>

        <FormSectionTitle :text="$t('projects.form.section.financial')" />
        <v-select
          density="compact"
          variant="outlined"
          multiple
          v-model="form.financingTypes.value.value as ProjectFinancingType[]"
          :items="Object.values(ProjectFinancingType)"
          :placeholder="$t('projects.form.section.financial')"
          :item-title="(item) => $t('projects.financing.' + item)"
          :item-value="(item) => item"
          :error-messages="form.financingTypes.errorMessage.value"
          @blur="form.financingTypes.handleChange(form.financingTypes.value.value)"
          return-object
        />
        <div class="Form__fieldCtn" v-if="otherFinancialTypeIsSelected">
          <label class="Form__label conditionnal">{{
            $t('projects.form.section.otherFinancingType')
          }}</label>
          <v-text-field
            density="compact"
            variant="outlined"
            v-model="form.otherFinancingType.value.value"
            :error-messages="form.otherFinancingType.errorMessage.value"
            @blur="form.otherFinancingType.handleChange"
          />
        </div>

        <v-tooltip location="start" :text="$t('projects.form.section.actorInChargeDisclaimer')">
          <template v-slot:activator="{ props }">
            <div>
              <FormSectionTitle :text="$t('projects.form.section.actorInCharge')" v-bind="props" />
              <div class="d-flex text-caption">
                {{ $t('projects.form.alterTexts.noActorInCharge') }}
                <v-checkbox
                  hide-details
                  density="compact"
                  class="ml-1 mt-0 pa-0"
                  :ripple="false"
                  v-model="projectHasNoActorInCharge"
                ></v-checkbox>
              </div>
            </div>
          </template>
        </v-tooltip>

        <v-select
          v-if="!projectHasNoActorInCharge"
          multiple
          density="compact"
          variant="outlined"
          v-model="form.actorsInCharge.value.value as Partial<Actor>[]"
          :items="actors"
          :placeholder="$t('projects.form.section.actorInCharge')"
          :item-title="(item) => item.name"
          item-value="@id"
          :error-messages="form.actorsInCharge.errorMessage.value"
          @blur="form.actorsInCharge.handleChange(form.actorsInCharge.value.value)"
          return-object
        />
        <v-text-field
          v-else
          density="compact"
          variant="outlined"
          :placeholder="$t('projects.form.fields.otherActor.label')"
          v-model="form.otherActorInCharge.value.value"
          :error-messages="
            projectHasNoActorInCharge && !form.otherActorInCharge.value.value
              ? $t('projects.form.errorMessages.noActor')
              : ''
          "
          @blur="form.otherActorInCharge.handleChange"
        />

        <v-tooltip location="start" :text="$t('projects.form.section.projectOwnerDisclaimer')">
          <template v-slot:activator="{ props }">
            <div>
              <FormSectionTitle :text="$t('projects.form.section.projectOwner')" v-bind="props" />
              <div class="d-flex text-caption">
                {{ $t('projects.form.alterTexts.noProjectOwner') }}
                <v-checkbox
                  hide-details
                  density="compact"
                  class="ml-1 mt-0 pa-0"
                  :ripple="false"
                  :disabled="actorLocked"
                  v-model="projectHasNoOwner"
                ></v-checkbox>
              </div>
            </div>
          </template>
        </v-tooltip>

        <v-select
          v-if="!projectHasNoOwner"
          density="compact"
          variant="outlined"
          :disabled="actorLocked"
          v-model="form.actor.value.value as Actor"
          :items="actors"
          item-title="name"
          item-value="@id"
          :error-messages="form.actor.errorMessage.value"
          :placeholder="$t('projects.form.section.projectOwner')"
          @blur="form.actor.handleChange(form.actor.value.value)"
          :clearable="!actorLocked"
          return-object
        />
        <v-text-field
          v-else
          density="compact"
          variant="outlined"
          v-model="form.otherActor.value.value"
          :placeholder="$t('projects.form.fields.otherActor.label')"
          :error-messages="
            projectHasNoOwner && !form.otherActor.value.value
              ? $t('projects.form.errorMessages.noActor')
              : ''
          "
          @blur="form.otherActor.handleChange"
        />

        <FormSectionTitle :text="$t('projects.form.section.focalPoint')" />

        <div class="Form__fieldCtn">
          <label class="Form__label required">{{
            $t('projects.form.fields.focalPointName.label')
          }}</label>
          <v-text-field
            density="compact"
            variant="outlined"
            v-model="form.focalPointName.value.value"
            :placeholder="$t('projects.form.fields.focalPointName.label')"
            :error-messages="form.focalPointName.errorMessage.value"
            @blur="form.focalPointName.handleChange"
          />
        </div>
        <div class="Form__fieldCtn">
          <label class="Form__label">{{
            $t('projects.form.fields.focalPointPosition.label')
          }}</label>
          <v-text-field
            density="compact"
            variant="outlined"
            v-model="form.focalPointPosition.value.value"
            :placeholder="$t('projects.form.fields.focalPointPosition.label')"
            :error-messages="form.focalPointPosition.errorMessage.value"
            @blur="form.focalPointPosition.handleChange"
          />
        </div>
        <div class="Form__fieldCtn">
          <label class="Form__label">{{ $t('projects.form.fields.focalPointEmail.label') }}</label>
          <v-text-field
            density="compact"
            variant="outlined"
            v-model="form.focalPointEmail.value.value"
            :placeholder="$t('projects.form.fields.focalPointEmail.label')"
            :error-messages="form.focalPointEmail.errorMessage.value"
            @blur="form.focalPointEmail.handleChange"
          />
        </div>
        <div class="Form__fieldCtn">
          <label class="Form__label">{{ $t('projects.form.fields.focalPointTel.label') }}</label>
          <vue-tel-input
            v-model="(form.focalPointTel.value.value as string)"
            @validate="phoneValidation"
          ></vue-tel-input>
        </div>

        <v-divider color="main-grey" class="border-opacity-100"></v-divider>
        <FormSectionTitle :text="$t('projects.form.section.projectImages')" />
        <ImagesLoader @updateFiles="handleImagesUpdate" :existingImages="existingImages" />
        <FormSectionTitle :text="$t('projects.form.section.partnerLogos')" />
        <ImagesLoader
          @updateFiles="handleImagesPartnerUpdate"
          :existingImages="existingPartnerImages"
          :externalImagesLoader="false"
        />
        <FormSectionTitle :text="$t('projects.form.section.resources')" />
        <div class="Form__fieldCtn">
          <div class="Form__attachedFiles" v-if="existingResources.length || resourceFilesToUpload.length">
            <a
              v-for="(res, index) in existingResources"
              :key="res['@id'] ?? index"
              :href="(res.fileObject as BaseMediaObject).contentUrl"
              target="_blank"
              class="Form__existingFile mr-2 mb-2"
            >
              <v-icon icon="$folder" size="small" class="mr-1" />
                {{ (res.fileObject as FileObject).originalName ?? $t('forms.file') }}
              <v-icon icon="$close" size="x-small" class="ml-1" @click.prevent="existingResources.splice(index, 1)" />
            </a>
            <v-chip
              v-for="(file, index) in resourceFilesToUpload"
              :key="'new-' + index"
              closable
              size="small"
              class="mr-2 mb-2"
              @click:close="resourceFilesToUpload.splice(index, 1)"
            >
              {{ file.name }}
            </v-chip>
          </div>
          <v-file-input
            density="compact"
            variant="outlined"
            multiple
            accept=".pdf,.doc,.docx,.xlsx,.jpg,.jpeg,.png,.webp,.gif,.svg"
            :placeholder="$t('projects.form.fields.resources.label')"
            @update:model-value="(files) => resourceFilesToUpload.push(...(Array.isArray(files) ? files : files ? [files] : []))"
          />
        </div>
      </v-form>
    </template>
    <template #footer-left>
      <span class="text-action" @click="$emit('close')">{{ $t('forms.cancel') }}</span>
      <span v-show="isSubmitting" class="text-warning ml-3">{{ $t('forms.submitting') }}</span>
    </template>
    <template #footer-right>
      <v-btn type="submit" form="project-form" color="main-red" :loading="isSubmitting">
        {{ submitLabel }}
      </v-btn>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import ImagesLoader from '@/components/forms/ImagesLoader.vue'
import LocationSelector from '@/components/forms/LocationSelector.vue'
import TextEditor from '@/components/forms/TextEditor.vue'
import Modal from '@/components/global/Modal.vue'
import FormSectionTitle from '@/components/text-elements/FormSectionTitle.vue'
import { AdministrativeScope } from '@/models/enums/AdministrativeScope'
import { FormType } from '@/models/enums/app/FormType'
import { NotificationType } from '@/models/enums/app/NotificationType'
import { UserRoles } from '@/models/enums/auth/UserRoles'
import { BeneficiaryType } from '@/models/enums/contents/BeneficiaryType'
import { ODD } from '@/models/enums/contents/ODD'
import { ProjectFinancingType } from '@/models/enums/contents/ProjectFinancingType'
import { Status } from '@/models/enums/contents/Status'
import { TechnicalMaturity } from '@/models/enums/contents/TechnicalMaturity'
import { FinancialStatus } from '@/models/enums/contents/FinancialStatus'
import { Thematic } from '@/models/enums/contents/Thematic'
import type { Actor } from '@/models/interfaces/Actor'
import type { Admin1Boundary, Admin3Boundary } from '@/models/interfaces/AdminBoundaries'
import type { ContentImageFromUserFile } from '@/models/interfaces/ContentImage'
import type { GeoData } from '@/models/interfaces/geo/GeoData'
import type { BaseMediaObject } from '@/models/interfaces/object/MediaObject'
import { type Project, type ProjectSubmission } from '@/models/interfaces/Project'
import { i18n } from '@/plugins/i18n'
import { nestedObjectsToIri } from '@/services/api/ApiPlatformService'
import { onInvalidSubmit } from '@/services/forms/FormService'
import { addNotification } from '@/services/notifications/NotificationService'
import { ProjectFormService } from '@/services/projects/ProjectFormService'
import { useActorsStore } from '@/stores/actorsStore'
import { useActorTeamStore } from '@/stores/actorTeamStore'
import { useAdminBoundariesStore } from '@/stores/adminBoundariesStore'
import { useProjectStore } from '@/stores/projectStore'
import { useUserStore } from '@/stores/userStore'
import NewSubmission from '@/views/admin/components/form/NewSubmission.vue'
import { computed, onMounted, type Ref, ref } from 'vue'
import FileUploader from '@/services/files/FileUploader'
import type { ProjectResourceAttachment } from '@/models/interfaces/Project'
import { useI18n } from 'vue-i18n'
import type { FileObject } from '@/models/interfaces/object/FileObject'

const projectStore = useProjectStore()
const actorsStore = useActorsStore()
const teamStore = useActorTeamStore()
const adminBoundariesStore = useAdminBoundariesStore()
const userStore = useUserStore()
const { t } = useI18n()

const props = defineProps<{
  type: FormType
  project: Project | null
  isShown: boolean
}>()

const projectHasNoOwner = ref(false)

const existingLogo = ref<(BaseMediaObject | string)[]>([])
const existingImages = ref<(BaseMediaObject | string)[]>([])
const existingPartnerImages = ref<BaseMediaObject[]>([])
const existingResources = ref<ProjectResourceAttachment[]>([])
const resourceFilesToUpload = ref<globalThis.File[]>([])
let existingHostedImages: BaseMediaObject[] = []
let existingExternalImages: string[] = []
let existingHostedPartnerImages: BaseMediaObject[] = []
const imagesToUpload: Ref<ContentImageFromUserFile[]> = ref([])
const imagesPartnerToUpload: Ref<ContentImageFromUserFile[]> = ref([])
const newLogo: Ref<ContentImageFromUserFile[]> = ref([])

const submitLabel = computed(() => {
  if (userStore.userIsActorEditor() && props.type === FormType.CREATE) {
    return i18n.t('forms.continue')
  }
  return i18n.t('forms.' + props.type)
})

// Éditeurs et admins : tous les acteurs. Point focal / administrateur de page sans rôle d'éditeur :
// uniquement les acteurs dont ils gèrent la page (le serveur refuse les autres).
const actors = computed(() =>
  userStore.userIsAdmin() || userStore.userHasRole(UserRoles.EDITOR_PROJECTS)
    ? actorsStore.actorsList
    : actorsStore.actorsList.filter((actor) => userStore.userManagesActor(actor))
)

// Formulaire ouvert depuis la page d'un acteur : le responsable du projet est imposé (grisé).
const presetLocked = ref(false)
// Verrouillé aussi en modification pour qui n'est ni admin ni éditeur (point focal / admin de page).
const actorLocked = computed(
  () =>
    presetLocked.value ||
    !(userStore.userIsAdmin() || userStore.userHasRole(UserRoles.EDITOR_PROJECTS))
)

const otherThematicIsSelected = computed(() => {
  if (form.thematics.value?.value && Array.isArray(form.thematics.value?.value)) {
    return (form.thematics.value?.value as Thematic[]).includes(Thematic.OTHERS)
  }
  return false
})
const otherBeneficiaryIsSelected = computed(() => {
  if (form.beneficiaryTypes.value?.value && Array.isArray(form.beneficiaryTypes.value?.value)) {
    return (form.beneficiaryTypes.value?.value as BeneficiaryType[]).includes(
      BeneficiaryType.OTHERS
    )
  }
  return false
})
const otherFinancialTypeIsSelected = computed(() => {
  if (form.financingTypes.value?.value && Array.isArray(form.financingTypes.value?.value)) {
    return (form.financingTypes.value?.value as ProjectFinancingType[]).includes(
      ProjectFinancingType.OTHER
    )
  }
  return false
})

const showFinancialFields = computed(() => {
  const status = form.status.value?.value as Status | undefined
  return status === Status.PLANNED || status === Status.ONGOING
})

const residualGap = computed(() => {
  const total = form.totalBudget.value?.value as number | null | undefined
  const mobilized = form.mobilizedFunds.value?.value as number | null | undefined
  if (total == null || mobilized == null) return null
  return total - mobilized
})

const projectHasNoActorInCharge = ref(false)

const emit = defineEmits(['submitted', 'close'])

const { form, handleSubmit, isSubmitting } = ProjectFormService.getForm(props.project)
const activeAdminLevels = computed(() => {
  if (
    form.administrativeScopes.value?.value &&
    Array.isArray(form.administrativeScopes.value?.value)
  ) {
    return {
      admin1: (form.administrativeScopes.value?.value as AdministrativeScope[]).includes(
        AdministrativeScope.REGIONAL
      ),
      admin3: (form.administrativeScopes.value?.value as AdministrativeScope[]).includes(
        AdministrativeScope.CITY
      )
    }
  }
  return false
})

onMounted(async () => {
  await Promise.all([
    actorsStore.getAll(),
    adminBoundariesStore.getAdmin1(),
    adminBoundariesStore.getAdmin3()
  ])
  // Formulaire ouvert depuis la page d'un acteur : on pré-sélectionne cet acteur.
  if (!props.project && teamStore.presetActorId) {
    const preset = actors.value.find((actor) => actor.id === teamStore.presetActorId)
    if (preset) {
      form.actor.value.value = preset
      presetLocked.value = true
    }
  }
  if (props.project) {
    if (props.project.otherActor) {
      projectHasNoOwner.value = true
    }
    if (props.project.otherActorInCharge) {
      projectHasNoActorInCharge.value = true
    }
    existingLogo.value = props.project.logo ? [props.project.logo] : []
    existingImages.value = [...props.project.images, ...props.project.externalImages]
    existingHostedImages = props.project.images
    existingExternalImages = props.project.externalImages

    existingPartnerImages.value = props.project.partners
    existingHostedPartnerImages = props.project.partners
    existingResources.value = props.project.resources ?? []
  }
})

const formError = ref<boolean>(false)
let internationalPhoneNumber: string | null = null
function phoneValidation(phoneObject: any) {
  form.focalPointTel.value.value = phoneObject.nationalNumber
  internationalPhoneNumber = phoneObject.number
}

const submitForm = handleSubmit(
  async (values: Partial<Project | ProjectSubmission>) => {
    formError.value = false

    try {
      const newResourceFiles = await Promise.all(
        resourceFilesToUpload.value.map((file) => FileUploader.uploadFile(file))
      )

      const resources: ProjectResourceAttachment[] = [
        ...existingResources.value.map((r) => ({
          fileObject:
            typeof r.fileObject === 'string' ? r.fileObject : (r.fileObject as BaseMediaObject)['@id']
        })),
        ...newResourceFiles.map((f: any) => ({ fileObject: f['@id'] }))
      ]

      let projectSubmission: ProjectSubmission = nestedObjectsToIri(values)
      if ([FormType.EDIT, FormType.VALIDATE].includes(props.type) && props.project) {
        projectSubmission.id = props.project.id
      }

      projectSubmission = {
        ...projectSubmission,
        images: existingHostedImages,
        partners: existingHostedPartnerImages,
        resources,
        externalImages: existingExternalImages,
        logoToUpload: newLogo.value[0],
        imagesToUpload: [...imagesToUpload.value],
        imagesPartnerToUpload: [...imagesPartnerToUpload.value],
        focalPointTel: internationalPhoneNumber as string
      }

      const submittedProject = await projectStore.submitProject(projectSubmission, props.type)
      emit('submitted', submittedProject)
    } catch (error: any) {
      // Afficher l'erreur détaillée
      console.error('Erreur lors de la soumission:', error)
      
      let errorMessage = i18n.t('forms.errors')
      
      // Gérer les erreurs de validation du backend
      if (error.response?.data) {
        const errorData = error.response.data
        
        // Erreurs de validation Symfony/API Platform
        if (errorData.violations) {
          const violations = errorData.violations
          console.log('Violations de validation:', violations)
          errorMessage = violations.map((v: any) => `${v.propertyPath}: ${v.message}`).join('\n')
        }
        // Erreur avec message personnalisé
        else if (errorData.detail) {
          errorMessage = errorData.detail
        }
        // Erreur générique
        else if (errorData.message) {
          errorMessage = errorData.message
        }
      }
      
      addNotification(errorMessage, NotificationType.ERROR)
      formError.value = true
      onInvalidSubmit()
    }
  },
  (validationErrors) => {
    // Erreurs de validation du formulaire
    formError.value = true
    console.log('Erreurs de validation du formulaire:', validationErrors)
    
    // Afficher les erreurs de validation dans la notification
    let errorMessages = []
    if (validationErrors && typeof validationErrors === 'object') {
      for (const [field, errors] of Object.entries(validationErrors)) {
        if (Array.isArray(errors)) {
          errorMessages.push(`${field}: ${errors.join(', ')}`)
        }
      }
    }
    
    const message = errorMessages.length > 0 
      ? errorMessages.join('; ')
      : i18n.t('forms.errors')
    
    addNotification(message, NotificationType.ERROR)
    onInvalidSubmit()
  }
)

function handleImagesUpdate(lists: any) {
  imagesToUpload.value = lists.selectedFiles
  existingExternalImages = []
  existingHostedImages = []
  lists.existingImages.forEach((image: BaseMediaObject | string) => {
    if (typeof image === 'string') {
      existingExternalImages.push(image)
    } else {
      existingHostedImages.push(image)
    }
  })
}

function handleImagesPartnerUpdate(lists: any) {
  imagesPartnerToUpload.value = lists.selectedFiles
  existingHostedPartnerImages = []
  lists.existingImages.forEach((image: BaseMediaObject) => {
    existingHostedPartnerImages.push(image)
  })
}

function handleLogoUpdate(list: any) {
  newLogo.value = list.selectedFiles
}
</script>