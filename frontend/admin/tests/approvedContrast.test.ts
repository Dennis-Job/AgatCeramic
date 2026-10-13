import { describe, expect, test } from 'vitest'
import { partitionApprovedContrast } from '../e2e/contracts/approvedContrast'

const node = (fgColor = '#12b76a', bgColor = '#ecfdf3') => ({
  any: [{ id: 'color-contrast', data: { fgColor, bgColor } }],
  all: [],
  none: [],
})

describe('owner-approved contrast exceptions', () => {
  test('reports the exact approved pair separately without mutating raw axe evidence', () => {
    const raw = [{ id: 'color-contrast', nodes: [node()] }]
    const result = partitionApprovedContrast(raw)
    expect(result.violations).toEqual([])
    expect(result.approved).toEqual(raw)
    expect(raw[0].nodes).toHaveLength(1)
  })

  test('rejects a changed foreground, background or reversed pair', () => {
    for (const changed of [
      node('#13b76a'),
      node('#12b76a', '#ffffff'),
      node('#ecfdf3', '#12b76a'),
    ]) {
      const raw = [{ id: 'color-contrast', nodes: [changed] }]
      expect(partitionApprovedContrast(raw).violations).toEqual(raw)
    }
  })

  test('keeps an unapproved node in a mixed contrast violation', () => {
    const changed = node('#000001', '#000002')
    const result = partitionApprovedContrast([
      { id: 'color-contrast', nodes: [node(), changed] },
    ])
    expect(result.violations[0].nodes).toEqual([changed])
    expect(result.approved[0].nodes).toEqual([node()])
  })

  test('never accepts a structural violation even with approved color data', () => {
    const raw = [{ id: 'button-name', nodes: [node()] }]
    expect(partitionApprovedContrast(raw).violations).toEqual(raw)
    expect(partitionApprovedContrast(raw).approved).toEqual([])
  })

  test('fails closed on missing data, extra failing checks and empty findings', () => {
    const raw = [
      {
        id: 'color-contrast',
        nodes: [{ ...node(), any: [{ id: 'color-contrast', data: null }] }],
      },
      {
        id: 'color-contrast',
        nodes: [{ ...node(), all: [{ id: 'unexpected' }] }],
      },
      { id: 'color-contrast', nodes: [] },
    ]
    expect(partitionApprovedContrast(raw).violations).toEqual(raw)
    expect(partitionApprovedContrast(raw).approved).toEqual([])
  })
})
