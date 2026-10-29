import type { UserRoles } from '@/models/enums/auth/UserRoles'
import type { ManagedActor } from '@/models/interfaces/ActorTeam'
import type { Validateable } from '../common/Validateable'
import type { FileObject } from '../object/FileObject'

export interface User extends Validateable {
  id: number
  logo: FileObject
  firstName: string
  lastName: string
  fullName: string
  organisation: string
  position: string
  phone: string
  email: string
  roles: UserRoles[]
  requestedRoles: UserRoles[]
  hasSeenRequestedRoles: boolean
  /** Acteurs dont l'utilisateur est point focal ou administrateur de page (affectations en cours). */
  managedActors?: ManagedActor[]
}

export interface UserSubmission extends Omit<User, 'logo'> {
  logo: FileObject | string
}