export interface SiteLink {
  label: string
  to: string
}

export const siteNavigation: readonly SiteLink[] = [
  { label: 'Главная', to: '/#home' },
  { label: 'Каталог', to: '/#catalog' },
  { label: 'Материалы', to: '/#materials' },
  { label: 'О проекте', to: '/#about' },
]
