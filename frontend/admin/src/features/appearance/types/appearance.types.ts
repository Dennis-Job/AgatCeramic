export type SiteLink = { label: string; to: string }
export type SiteNavigationItem =
  SiteLink | { label: string; children: SiteLink[] }
export type NavigationEditorRow = {
  id: number
  label: string
  hasChildren: boolean
  to: string
  children: SiteLink[]
}
export type NavigationEditorDraft = { rows: NavigationEditorRow[] }
export type SiteAppearance = {
  has_unpublished_changes: boolean
  header: {
    topbar_left: string
    topbar_right: string
    logo_media_id: number | null
    logo_alt: string
    logo_url: string | null
    navigation: SiteNavigationItem[]
  }
  footer: {
    tagline: string
    navigation_links?: SiteLink[]
    social_links?: SiteLink[]
    explore_links: SiteLink[]
    message: {
      eyebrow: string
      text: string
      link_label: string
      link_url: string
    }
    bottom_left: string
    bottom_right: string
    bottom_right_url?: string
  }
}
export type AppearanceSection = 'header' | 'footer'
export type AppearancePanel = AppearanceSection | 'filters'
