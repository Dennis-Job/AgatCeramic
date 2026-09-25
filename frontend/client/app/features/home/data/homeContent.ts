export interface HomeSlide {
  id: string
  eyebrow: string
  title: string
  description: string
  image: string
  imageAlt: string
}

export interface MaterialCategory {
  id: string
  name: string
  shortDescription: string
  description: string
  image: string
  imageAlt: string
}

export const homeSlides: readonly HomeSlide[] = [
  {
    id: 'porcelain',
    eyebrow: 'AgatCeramic · Керамогранит',
    title: 'Пространство начинается с фактуры.',
    description:
      'Керамогранит, плитка и мозаика для интерьеров, в которых важна каждая деталь.',
    image: '/images/home/hero-porcelain.webp',
    imageAlt: 'Светлый керамогранит с фактурой камня в современном интерьере',
  },
  {
    id: 'tile',
    eyebrow: 'AgatCeramic · Плитка',
    title: 'Тепло материала. Тишина формы.',
    description:
      'От гладкой поверхности до выразительной ручной фактуры — найдите своё настроение.',
    image: '/images/home/category-tile.webp',
    imageAlt: 'Светлая керамическая плитка в интерьере ванной комнаты',
  },
  {
    id: 'mosaic',
    eyebrow: 'AgatCeramic · Мозаика',
    title: 'Акцент в каждой детали.',
    description:
      'Мелкий формат помогает выделить нишу, стену или другую важную часть пространства.',
    image: '/images/home/category-mosaic.webp',
    imageAlt: 'Мозаичная поверхность в спокойных природных оттенках',
  },
]

export const materialCategories: readonly MaterialCategory[] = [
  {
    id: 'porcelain',
    name: 'Керамогранит',
    shortDescription: 'Формат и выразительная фактура',
    description:
      'Крупные плоскости, фактура камня и спокойная геометрия — основа цельного интерьера.',
    image: '/images/home/category-porcelain.webp',
    imageAlt: 'Светлый керамогранит на полу современного интерьера',
  },
  {
    id: 'tile',
    name: 'Керамическая плитка',
    shortDescription: 'Поверхности с характером',
    description:
      'От мягкого блеска до матовой поверхности: плитка задаёт свет и ритм пространству.',
    image: '/images/home/category-tile.webp',
    imageAlt: 'Фактурная керамическая плитка светлого оттенка',
  },
  {
    id: 'mosaic',
    name: 'Мозаика',
    shortDescription: 'Детали, которые собирают образ',
    description:
      'Небольшие элементы помогают сделать акцент и подчеркнуть выбранную палитру.',
    image: '/images/home/category-mosaic.webp',
    imageAlt: 'Небольшие керамические элементы мозаики в ванной',
  },
]

export const materialGuide = [
  {
    title: 'Формат',
    description: 'Размер элемента влияет на ритм швов и восприятие помещения.',
  },
  {
    title: 'Поверхность',
    description:
      'Матовая, гладкая или рельефная фактура по-разному раскрывает свет.',
  },
  {
    title: 'Оттенок',
    description:
      'Тёплые и холодные тона помогают собрать цельную палитру интерьера.',
  },
  {
    title: 'Назначение',
    description:
      'При выборе важно учитывать место применения и свойства материала.',
  },
] as const
