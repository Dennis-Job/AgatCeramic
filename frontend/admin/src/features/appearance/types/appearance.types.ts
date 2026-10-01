export type SiteLink = { label: string; to: string }
export type SiteAppearance = {
  has_unpublished_changes: boolean
  header: {
    topbar_left: string
    topbar_right: string
    logo_media_id: number | null
    logo_alt: string
    logo_url: string | null
    navigation: SiteLink[]
  }
  footer: {
    tagline: string
    explore_links: SiteLink[]
    message: {
      eyebrow: string
      text: string
      link_label: string
      link_url: string
    }
    bottom_left: string
    bottom_right: string
  }
}
export type AppearanceSection = 'header' | 'footer'
export type AppearancePanel = AppearanceSection | 'filters'
