type ContrastNode = {
  any: { id: string; data: unknown }[]
  all: unknown[]
  none: unknown[]
}

// Exact owner-approved foreground/background pairs, not a rule-wide axe opt-out.
// Keep WCAG findings as evidence; any new pair still fails accessibility checks.
export const approvedContrastPairs = [
  ['#12b76a', '#ecfdf3'],
  ['#0ba5ec', '#f0f9ff'],
  ['#ffffff', '#f04438'],
  ['#f04438', '#ffffff'],
  ['#f79009', '#fffaeb'],
  ['#f04438', '#fef3f2'],
  ['#0ba5ec', '#e0f2fe'],
  ['#0ba5ec', '#f2f4f7'],
  ['#667085', '#ebf4ff'],
  ['#667085', '#d9eaff'],
] as const

function isApproved(node: ContrastNode): boolean {
  if (node.any.length !== 1 || node.all.length || node.none.length) return false
  const check = node.any[0]
  if (
    check.id !== 'color-contrast' ||
    !check.data ||
    typeof check.data !== 'object'
  )
    return false
  const data = check.data as { fgColor?: string; bgColor?: string }
  return approvedContrastPairs.some(
    ([foreground, background]) =>
      data.fgColor === foreground && data.bgColor === background,
  )
}

export function partitionApprovedContrast<
  T extends { id: string; nodes: ContrastNode[] },
>(violations: T[]) {
  const remaining: T[] = []
  const approved: T[] = []
  for (const violation of violations) {
    if (violation.id !== 'color-contrast' || !violation.nodes.length) {
      remaining.push(violation)
      continue
    }
    const accepted = violation.nodes.filter(isApproved)
    const rejected = violation.nodes.filter((node) => !isApproved(node))
    if (accepted.length) approved.push({ ...violation, nodes: accepted })
    if (rejected.length) remaining.push({ ...violation, nodes: rejected })
  }
  return { violations: remaining, approved }
}
