export interface PlatformService {
  id: string
  name: string
  /** Clé i18n du texte descriptif affiché sur la carte */
  descriptionKey: string
  /** Chemin du logo. Un monogramme s'affiche si le fichier est absent. */
  logo: string
  /** Lien vers le site ou l'application. Laisser vide = carte « Bientôt disponible ». */
  url: string
  /** Nom d'une couleur du thème pucCustomTheme (liseré et monogramme) */
  color: 'main-blue' | 'bright-blue' | 'main-green' | 'main-yellow' | 'main-red'
}

// TODO: renseigner l'url de SmartCity et déposer les logos manquants
export const SERVICES: PlatformService[] = [
  {
    id: 'banoc',
    name: 'BANOC',
    descriptionKey: 'services.items.banoc.description',
    logo: '/img/services/banoc.jpeg',
    url: 'https://banoc.cm/',
    color: 'main-blue'
  },
  {
    id: 'cadastre-polyvalent',
    name: 'Cadastre Polyvalent',
    descriptionKey: 'services.items.cadastre-polyvalent.description',
    logo: '/img/services/cadastre_polyvalent.jpeg',
    url: 'https://soa.position.cm/',
    color: 'main-green'
  },
  {
    id: 'sigcom',
    name: 'SIGCOM',
    descriptionKey: 'services.items.sigcom.description',
    logo: '/img/services/sigcom.jpeg',
    url: 'https://sigcom.minddevel.cm/',
    color: 'bright-blue'
  },
  {
    id: 'smartcity',
    name: 'MboaCity',
    descriptionKey: 'services.items.smartcity.description',
    logo: '/img/services/smartcity.png',
    url: 'https://smartcity-cameroun.flutterflow.app',
    color: 'main-yellow'
  }
]