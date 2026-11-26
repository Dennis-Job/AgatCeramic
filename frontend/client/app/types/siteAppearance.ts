export interface SiteLink {
  label: string
  to: string
}

export type SiteNavigationItem =
  SiteLink | { label: string; children: SiteLink[] }

export interface SiteLinkDto {
  label: string
  to: string
}

export type SiteNavigationItemDto =
  SiteLinkDto | { label: string; children: SiteLinkDto[] }

export interface SiteAppearanceDto {
  header: {
    topbar_left: string
    topbar_right: string
    menu_note?: string | null
    navigation: SiteNavigationItemDto[]
    logo_url: string | null
    logo_alt: string | null
  }
  footer: {
    tagline: string
    navigation_links?: SiteLinkDto[]
    social_links?: SiteLinkDto[]
    explore_links: SiteLinkDto[]
    message: {
      eyebrow: string
      text: string
      link_label: string
      link_url: string
    }
    bottom_left: string
    bottom_right: string
    bottom_right_url?: string | null
  }
}

export interface SiteAppearance {
  header: {
    topbarLeft: string
    topbarRight: string
    menuNote?: string
    navigation: SiteNavigationItem[]
    logoUrl: string
    logoAlt: string
  }
  footer: {
    tagline: string
    navigationLinks?: SiteLink[]
    socialLinks?: SiteLink[]
    exploreLinks: SiteLink[]
    message: {
      eyebrow: string
      text: string
      linkLabel: string
      linkUrl: string | null
    }
    bottomLeft: string
    bottomRight: string
    bottomRightUrl?: string | null
  }
}
