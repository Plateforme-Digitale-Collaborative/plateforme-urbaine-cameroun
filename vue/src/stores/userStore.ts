import { DialogKey } from '@/models/enums/app/DialogKey'
import { StoresList } from '@/models/enums/app/StoresList'
import { ActorAssignmentRole } from '@/models/enums/actors/ActorTeam'
import { UserRoles } from '@/models/enums/auth/UserRoles'
import type {
  EmailVerifierValues,
  SignInValues,
  SignUpValues
} from '@/models/interfaces/auth/AuthenticationsValues'
import type { User, UserSubmission } from '@/models/interfaces/auth/User'
import type { ManagedActor } from '@/models/interfaces/ActorTeam'
import type { FileObject } from '@/models/interfaces/object/FileObject'
import FileUploader from '@/services/files/FileUploader'
import { AuthenticationService } from '@/services/userAndAuth/AuthenticationService'
import JwtCookie from '@/services/userAndAuth/JWTCookie'
import { UserService } from '@/services/userAndAuth/UserService'
import * as Sentry from '@sentry/browser'
import { isAxiosError } from 'axios'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApplicationStore } from './applicationStore'

/** Un acteur, sa référence API ou son IRI. */
type ActorRef = string | { id?: string; '@id'?: string } | null | undefined

export const useUserStore = defineStore(StoresList.USER, () => {
  const router = useRouter()
  const route = useRoute()
  const loginCheck = ref(false)
  const currentUser = ref<User | null>(null)
  const errorWhileSignInOrSignUp = ref(false)
  const invalidAccount = ref(false)
  const userIsLogged = computed(() => currentUser.value !== null)
  const userHasRole = (role: UserRoles) =>
    userIsLogged.value && currentUser.value?.roles.includes(role)
  const userIsAdmin = () => userIsLogged.value && userHasRole(UserRoles.ADMIN)
  const userIsActorEditor = () => userHasRole(UserRoles.EDITOR_ACTORS)
  const userIsDiverCitySpaceAdmin = () => userHasRole(UserRoles.DIVERCITY_SPACE_ADMIN)
  const userIsEditor = () => {
    return (
      userIsLogged.value &&
      (userHasRole(UserRoles.EDITOR_PROJECTS) ||
        userIsActorEditor() ||
        userHasRole(UserRoles.EDITOR_RESSOURCES) ||
        userHasRole(UserRoles.EDITOR_DATA))
    )
  }

  // --- Pages d'acteurs : point focal / administrateur de page ---
  const managedActors = computed<ManagedActor[]>(() => currentUser.value?.managedActors ?? [])
  const userHasManagedActors = computed(() => managedActors.value.length > 0)
  /** Accepte un acteur complet ({ id }), une référence API ({ '@id': '/api/actors/{id}' }) ou un IRI. */
  const actorIdOf = (actor?: ActorRef): string | undefined =>
    typeof actor === 'string' ? actor.split('/').pop() : (actor?.id ?? actor?.['@id']?.split('/').pop())
  /** Rôle de l'utilisateur sur cet acteur, ou null s'il n'en est ni point focal ni administrateur de page. */
  const userActorRole = (
    actor?: ActorRef
  ): ActorAssignmentRole | null => {
    const id = actorIdOf(actor)
    if (!id || !userIsLogged.value) return null
    return managedActors.value.find((managed) => managed.actorId === id)?.role ?? null
  }
  const userIsFocalPointOf = (actor?: ActorRef) =>
    userActorRole(actor) === ActorAssignmentRole.FOCAL_POINT
  const userIsPageAdminOf = (actor?: ActorRef) =>
    userActorRole(actor) === ActorAssignmentRole.PAGE_ADMIN
  /** Peut agir sur la page de cet acteur (les modifications d'un administrateur de page passent par validation). */
  const userManagesActor = (actor?: ActorRef) =>
    userActorRole(actor) !== null
  /** Peut valider les modifications / nommer les administrateurs de page de cet acteur. */
  const userCanValidateFor = (actor?: ActorRef) =>
    userIsAdmin() || userIsFocalPointOf(actor)

  const signIn = async (values: SignInValues, hideDialog = true) => {
    errorWhileSignInOrSignUp.value = false
    invalidAccount.value = false
    try {
      await AuthenticationService.signIn(values)
      if (!values.stayLoggedIn) {
        await JwtCookie.deleteRefreshToken()
      }
      await setCurrentUser()
      errorWhileSignInOrSignUp.value = false
      invalidAccount.value = false
      if (currentUser.value?.hasSeenRequestedRoles === false) {
        await UserService.patchUser({ hasSeenRequestedRoles: true }, currentUser.value.id)
        return await router.replace({
          query: { ...route.query, dialog: DialogKey.AUTH_BECOME_MEMBER_ROLES }
        })
      }
      if (hideDialog) {
        const isClosingSignInDialog = route.query.dialog === DialogKey.AUTH_SIGN_IN
        const redirectTo = route.query.redirect as string | undefined
        if (isClosingSignInDialog && redirectTo) {
          return await router.replace({
            name: redirectTo,
            query: { ...route.query, dialog: undefined, redirect: undefined }
          })
        }
        await router.replace({ query: { ...route.query, dialog: undefined } })
      }
    } catch (err) {
      Sentry.captureException(err)

      if (isAxiosError(err) && err.response?.status === 401) {
        const message = err.response.data?.message
        invalidAccount.value =
          typeof message === 'string' && message.toLowerCase().includes('not validated')
        errorWhileSignInOrSignUp.value = !invalidAccount.value
        return
      }
      errorWhileSignInOrSignUp.value = true
    }
  }

  const setCurrentUser = async () => {
    await getAuthenticatedUser()
    const appStore = useApplicationStore()
    appStore.getLikesList()
  }

  const getAuthenticatedUser = async () => {
    currentUser.value = (await AuthenticationService.getAuthenticatedUser()).data
  }

  /** Recharge le profil (rôles, acteurs gérés...) sans passer par une reconnexion. */
  const refreshCurrentUser = async () => {
    if (userIsLogged.value) {
      await getAuthenticatedUser()
    }
  }

  const signUp = async (values: SignUpValues) => {
    try {
      await UserService.createUser(values)
      await router.replace({
        query: { ...route.query, dialog: DialogKey.AUTH_BECOME_MEMBER_THANKS }
      })
    } catch (err) {
      errorWhileSignInOrSignUp.value = true
      Sentry.captureException(err)
    }
  }

  const signOut = async () => {
    currentUser.value = null
    JwtCookie.clearCookies()
    router.push({ name: 'home' })
  }

  const verifyEmail = async (emailVerifierValues: EmailVerifierValues) => {
    await AuthenticationService.verifyEmail(emailVerifierValues)
    if (userIsLogged.value) {
      await getAuthenticatedUser()
    }
  }

  const checkAuthenticated = async () => {
    const jwtCookieIsValid = await JwtCookie.isValid()
    if (jwtCookieIsValid) {
      await setCurrentUser()
    }
    loginCheck.value = true
  }

  const patchUser = async (
    values: Partial<UserSubmission>,
    updateLogo = false,
    logo: File | null = null
  ) => {
    if (values.logo && (values.logo as FileObject)['@id']) {
      values.logo = (values.logo as FileObject)['@id']
    }
    if (updateLogo && logo) {
      const newLogo = await FileUploader.uploadFile(logo)
      values.logo = newLogo['@id']
    }
    await UserService.patchUser(values as User, currentUser.value!.id)
    setCurrentUser()

    const isClosingBecomeMemberRolesDialog = route.query.dialog === DialogKey.AUTH_BECOME_MEMBER_ROLES
    const redirectTo = route.query.redirect as string | undefined
    if (isClosingBecomeMemberRolesDialog && redirectTo) {
      return router.replace({
        name: redirectTo,
        query: { ...route.query, dialog: undefined, redirect: undefined }
      })
    }
    router.replace({ query: { ...route.query, dialog: undefined } })
  }
  return {
    loginCheck,
    userIsLogged,
    userIsAdmin,
    userIsEditor,
    userHasRole,
    userIsActorEditor,
    userIsDiverCitySpaceAdmin,
    managedActors,
    userHasManagedActors,
    userActorRole,
    userIsFocalPointOf,
    userIsPageAdminOf,
    userManagesActor,
    userCanValidateFor,
    refreshCurrentUser,
    currentUser,
    errorWhileSignInOrSignUp,
    invalidAccount,
    signIn,
    signUp,
    signOut,
    verifyEmail,
    checkAuthenticated,
    patchUser
  }
})