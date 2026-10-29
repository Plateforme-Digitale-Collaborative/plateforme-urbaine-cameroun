import { AdministrativeScope } from '@/models/enums/AdministrativeScope'
import { ResourceFormat } from '@/models/enums/contents/ResourceFormat'
import { ResourceType } from '@/models/enums/contents/ResourceType'
import type { Resource } from '@/models/interfaces/Resource'
import { i18n } from '@/plugins/i18n'
import { CommonZodSchema } from '@/services/forms/CommonZodSchema'
import { toTypedSchema } from '@vee-validate/zod'
import { useField, useForm } from 'vee-validate'
import { z } from 'zod'

export class ResourceFormService {
  static getForm(resource: Resource | null) {
    const zodModels = CommonZodSchema.getDefinitions()
    // @ts-expect-error - zod object.
    const resourceSchema: z.ZodType<Partial<Resource>> = z
      .object({
        name: z
          .string({ required_error: i18n.t('forms.errorMessages.required') })
          .min(1, { message: i18n.t('forms.errorMessages.required') }),
        description: zodModels.descriptionRequired,
        type: z.nativeEnum(ResourceType, {
          required_error: i18n.t('forms.errorMessages.required')
        }),
        otherType: z.string().optional(),
        actor: zodModels.symfonyRelation.nullable().optional(),
        administrativeScopes: z.array(z.nativeEnum(AdministrativeScope)).optional(),
        admin1List: zodModels.admin1Boundaries.optional(),
        admin3List: zodModels.admin3Boundaries.optional(),
        geoData: zodModels.geoDataNullable.optional(),
        startAt: z.coerce.date().nullable().optional(),
        endAt: z.coerce.date().nullable().optional(),
        file: zodModels.file.nullable().optional(),
        link: zodModels.website.nullable().optional(),
        format: z.nativeEnum(ResourceFormat, {
          required_error: i18n.t('forms.errorMessages.required')
        }),
        author: z
          .string({ required_error: i18n.t('forms.errorMessages.required') })
          .min(1, { message: i18n.t('forms.errorMessages.required') }),
        thematics: zodModels.thematics,
        otherThematic: z.string().optional(),
        odds: zodModels.odds,
        banoc: zodModels.banoc,
        banocUrl: zodModels.banocUrl
      })
      .refine((data) => data.type !== ResourceType.EVENTS || Boolean(data.startAt), {
        message: i18n.t('forms.errorMessages.required'),
        path: ['startAt']
      })
      .refine(
        (data) => {
          const requiredTypes = [
            ResourceType.RAPPORTS,
            ResourceType.REGULATIONS,
            ResourceType.OTHERS
          ]

          if (requiredTypes.includes(data.type)) {
            return data.administrativeScopes !== undefined
          }

          return true
        },
        {
          message: i18n.t('forms.errorMessages.required'),
          path: ['administrativeScopes']
        }
      )
      .superRefine((data, ctx) => {
        if (!data.file && !data.link) {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ['file'],
            message: i18n.t('resources.form.errorMessages.atLeastOneField')
          })
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ['link'],
            message: i18n.t('resources.form.errorMessages.atLeastOneField')
          })
        }
      })

    const { errors, handleSubmit, isSubmitting } = useForm<Partial<Resource>>({
      initialValues: resource,
      validationSchema: toTypedSchema(resourceSchema)
    })

    const form: any = {
      name: useField('name'),
      description: useField('description'),
      type: useField('type'),
      otherType: useField('otherType'),
      actor: useField('actor'),
      file: useField('file'),
      format: useField('format'),
      administrativeScopes: useField('administrativeScopes'),
      admin1List: useField('admin1List'),
      admin3List: useField('admin3List'),
      geoData: useField('geoData'),
      startAt: useField('startAt'),
      endAt: useField('endAt'),
      author: useField('author'),
      thematics: useField('thematics'),
      otherThematic: useField('otherThematic'),
      odds: useField('odds'),
      link: useField('link'),
      banoc: useField('banoc'),
      banocUrl: useField('banocUrl')
    }

    return { form, errors, handleSubmit, isSubmitting }
  }
}