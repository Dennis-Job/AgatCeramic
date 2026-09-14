import { computed, onScopeDispose, ref } from 'vue'

type ImportStatus = 'pending' | 'processing' | 'completed' | 'failed'
type ImportJob = { id: number; status: ImportStatus }
type Download = { blob: Blob; filename: string }

type FileImportOptions<T extends ImportJob> = {
  upload: (file: File) => Promise<T>
  getStatus: (id: number) => Promise<T>
  validate: (file: File) => string
  uploadError: string
  onCompleted: () => void
  pollInterval?: number
}

export function useFileImport<T extends ImportJob>(
  options: FileImportOptions<T>,
) {
  const file = ref<File | null>(null)
  const result = ref<T | null>(null)
  const uploading = ref(false)
  const downloading = ref(false)
  const error = ref('')
  const notice = ref('')
  const pollingError = ref(false)
  let timer: ReturnType<typeof setTimeout> | undefined
  let disposed = false

  const finished = computed(
    () =>
      result.value?.status === 'completed' || result.value?.status === 'failed',
  )
  const busy = computed(
    () =>
      uploading.value ||
      result.value?.status === 'pending' ||
      result.value?.status === 'processing',
  )

  function clearFeedback() {
    error.value = ''
    notice.value = ''
  }

  function selectFile(selected: File | null) {
    file.value = selected
    clearFeedback()
    if (finished.value) result.value = null
  }

  function resetSelection(clearFinishedResult = false) {
    file.value = null
    clearFeedback()
    if (clearFinishedResult && finished.value) result.value = null
  }

  async function poll(id: number) {
    if (disposed) return
    pollingError.value = false
    try {
      const current = await options.getStatus(id)
      if (disposed) return
      result.value = current
      if (current.status === 'completed' || current.status === 'failed') {
        options.onCompleted()
        return
      }
      timer = setTimeout(() => {
        void poll(id)
      }, options.pollInterval ?? 1500)
    } catch {
      if (!disposed) pollingError.value = true
    }
  }

  async function upload() {
    if (!file.value || busy.value) return
    clearFeedback()
    const validationError = options.validate(file.value)
    if (validationError) {
      error.value = validationError
      return
    }
    uploading.value = true
    result.value = null
    try {
      result.value = await options.upload(file.value)
      void poll(result.value.id)
    } catch (reason) {
      error.value =
        reason instanceof Error ? reason.message : options.uploadError
    } finally {
      uploading.value = false
    }
  }

  async function download(
    request: () => Promise<Download>,
    successNotice: string | (() => string),
    failureMessage: string,
    preserveNotice = false,
  ) {
    if (downloading.value) return
    downloading.value = true
    error.value = ''
    if (!preserveNotice) notice.value = ''
    try {
      saveDownload(await request())
      notice.value =
        typeof successNotice === 'function' ? successNotice() : successNotice
    } catch (reason) {
      error.value = reason instanceof Error ? reason.message : failureMessage
    } finally {
      downloading.value = false
    }
  }

  function dispose() {
    disposed = true
    if (timer) clearTimeout(timer)
  }

  onScopeDispose(dispose)

  return {
    file,
    result,
    uploading,
    downloading,
    error,
    notice,
    pollingError,
    busy,
    finished,
    clearFeedback,
    selectFile,
    resetSelection,
    upload,
    poll,
    download,
    dispose,
  }
}

function saveDownload(download: Download) {
  const url = URL.createObjectURL(download.blob)
  const link = document.createElement('a')
  link.href = url
  link.download = download.filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
