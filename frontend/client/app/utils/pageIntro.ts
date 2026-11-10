import type { PageIntroImage } from '~/types/contentPage'
import { imageUrl } from '~/services/homePage'

const illustrations: Record<string, { description: string; alt: string }> = {
  catalog: {
    description: 'Керамика. Фактуры. Пространство.',
    alt: 'Композиция из образцов травертина, серого камня и рельефной белой керамики',
  },
  about: {
    description: 'Материалы и внимание к деталям.',
    alt: 'Образцы керамики и архитектурный чертёж',
  },
  contacts: {
    description: 'Будем рады вашему обращению.',
    alt: 'Керамические образцы на выставочном стенде',
  },
  cooperation: {
    description: 'Создадим пространство вместе.',
    alt: 'Согласованные палитры керамических материалов',
  },
  rezka: {
    description: 'Точная геометрия. Чистые линии.',
    alt: 'Керамические плиты с точными срезами и обработанными краями',
  },
  rospis: {
    description: 'Керамика с индивидуальным характером.',
    alt: 'Расписная керамическая плитка, кисть и палитра',
  },
  delivery: {
    description: 'Бережно к материалам. Внимательно к деталям.',
    alt: 'Керамические образцы в защитной упаковке',
  },
  payment: {
    description: 'Детали заказа — в прямом диалоге.',
    alt: 'Образцы керамики, лист заказа и ручка',
  },
  designers: {
    description: 'Материалы для ваших идей.',
    alt: 'Палитра керамических материалов и архитектурный чертёж',
  },
}

export function defaultPageIntro(slug: string, description = '') {
  const key = Object.hasOwn(illustrations, slug) ? slug : 'catalog'
  const illustration = illustrations[key]!
  return {
    imageUrl: `/images/page-intro/${key}-wide.webp`,
    imageAlt: illustration.alt,
    description: Object.hasOwn(illustrations, slug)
      ? illustration.description
      : description,
  }
}

export function mapPageIntro(
  intro: { image_url: string | null; image_alt: string } | null | undefined,
  base: string,
): PageIntroImage | null {
  if (!intro) return null
  const url = imageUrl(intro.image_url, base)
  return url ? { imageUrl: url, imageAlt: intro.image_alt || '' } : null
}
