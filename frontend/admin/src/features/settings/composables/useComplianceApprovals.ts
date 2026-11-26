import { ref } from 'vue'
import { createComplianceApproval } from '../services/settings'
import type {
  ComplianceApproval,
  RunSettingsAction,
} from '../types/settings.types'

const emptyApproval = () => ({
  role: 'business_owner' as ComplianceApproval['role'],
  reviewer_name: '',
  decision: 'approved' as ComplianceApproval['decision'],
  decided_on: '',
  document_version_id: '',
})

export function useComplianceApprovals(run: RunSettingsAction) {
  const approvals = ref<ComplianceApproval[]>([])
  const approvalForm = ref(emptyApproval())

  function addApproval() {
    return run(
      async () => {
        const approval = await createComplianceApproval({
          role: approvalForm.value.role,
          reviewer_name: approvalForm.value.reviewer_name,
          decision: approvalForm.value.decision,
          decided_on: approvalForm.value.decided_on,
          document_version_id: Number(approvalForm.value.document_version_id),
        })
        approvals.value.unshift(approval)
        approvalForm.value = emptyApproval()
      },
      'Решение зафиксировано в журнале.',
      'approval',
    )
  }

  return { approvals, approvalForm, addApproval }
}
