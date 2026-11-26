import type {
  SiteAppearance,
  SiteAppearanceDto,
  SiteLink,
  SiteLinkDto,
  SiteNavigationItem,
  SiteNavigationItemDto,
} from '~/types/siteAppearance'
import { searchNavigation } from '~/utils/navigation'
import { imageUrl, safeLink } from '~/utils/siteUrls'

function mapLinks(links: SiteLinkDto[]): SiteLink[] {
  return links.flatMap((link) => {
    const to = safeLink(link.to)
    return to && link.label ? [{ label: link.label, to }] : []
  })
}

function mapNavigation(items: SiteNavigationItemDto[]): SiteNavigationItem[] {
  return items.flatMap<SiteNavigationItem>((item) => {
    if ('children' in item) {
      const children = Array.isArray(item.children)
        ? mapLinks(item.children)
        : []
      return item.label && children.length
        ? [{ label: item.label, children }]
        : []
    }
    return mapLinks([item])
  })
}

export function mapSiteAppearance(
  dto: SiteAppearanceDto,
  apiBase: string,
): SiteAppearance {
  return {
    header: {
      topbarLeft: dto.header.topbar_left,
      topbarRight: dto.header.topbar_right,
      menuNote:
        dto.header.menu_note === undefined
          ? dto.footer.tagline
          : (dto.header.menu_note ?? ''),
      navigation: mapNavigation(dto.header.navigation),
      logoUrl: imageUrl(dto.header.logo_url, apiBase),
      logoAlt: dto.header.logo_alt || 'AgatCeramic',
    },
    footer: {
      tagline: dto.footer.tagline,
      navigationLinks:
        dto.footer.navigation_links === undefined
          ? searchNavigation(mapNavigation(dto.header.navigation), '')
          : mapLinks(dto.footer.navigation_links),
      socialLinks: mapLinks(dto.footer.social_links ?? []),
      exploreLinks: mapLinks(dto.footer.explore_links),
      message: {
        eyebrow: dto.footer.message.eyebrow,
        text: dto.footer.message.text,
        linkLabel: dto.footer.message.link_label,
        linkUrl: safeLink(dto.footer.message.link_url),
      },
      bottomLeft: dto.footer.bottom_left,
      bottomRight: dto.footer.bottom_right,
      bottomRightUrl: safeLink(dto.footer.bottom_right_url),
    },
  }
}

export async function fetchSiteAppearance(
  requestBase: string,
  publicBase: string,
): Promise<SiteAppearance> {
  const response = await $fetch<{
    data: SiteAppearanceDto
  }>(`${requestBase}/site-appearance`)
  return mapSiteAppearance(response.data, publicBase)
}
