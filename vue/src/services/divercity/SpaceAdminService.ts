import { apiClient } from '@/plugins/axios/api'

export interface SpaceAdminItem {
  '@id': string
  user: string
  space: string
}

export class SpaceAdminService {
  static async assign(userIri: string, spaceIri: string): Promise<void> {
    await apiClient.post('/api/space_admins', { user: userIri, space: spaceIri })
  }

  static async revoke(userIri: string, spaceIri: string): Promise<void> {
    const data = (
      await apiClient.get('/api/space_admins', {
        params: { user: userIri, space: spaceIri },
        headers: { accept: 'application/ld+json' }
      })
    ).data

    const members: SpaceAdminItem[] = data['hydra:member'] ?? data['member'] ?? []

    await Promise.all(members.map((item) => apiClient.delete(item['@id'])))
  }
}