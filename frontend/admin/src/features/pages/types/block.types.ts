import type { HomePageContent } from '../../homepage/types/homepage.types'

export type BodyBlockType =
  'marquee' | 'categories' | 'materials' | 'promo' | 'about' | 'guide'
export type BodyContent = Pick<HomePageContent, BodyBlockType>
export type BlockData = BodyContent & {
  hero: { slider_id: number | null }
  text: { title: string; body: string }
  stores: { title: string }
  catalog: { title: string; description: string }
}
export type BlockType = keyof BlockData
export type PageBlock = {
  [T in BlockType]: {
    id: string
    type: T
    enabled: boolean
    data: BlockData[T]
  }
}[BlockType]
