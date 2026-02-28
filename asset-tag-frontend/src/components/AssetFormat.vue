<script setup lang="ts">
import { ref, nextTick } from 'vue'
import QRCode from 'qrcode'
import html2canvas from 'html2canvas'
import Swal from 'sweetalert2'
import api from '@/services/api'

interface Asset {
  id: number
  uniqueCode?: string
  category?: { name: string }
  company?: {
    name: string
    code?: string
    logo?: string
  }
  person_in_charge?: string
  department?: {
    id: number
    name: string
  }
  specs?: string
  asset_code?: { control_number: string }
  invoice_date?: string
}

const showTagModal = ref(false)
const taggingAsset = ref<Asset | null>(null)
const qrCodeDataUrl = ref('')
const captureRef = ref<HTMLElement | null>(null)
const isReprint = ref(false)
const isLoading = ref(false)
const isAutoDownloading = ref(false) // hides overlay during silent auto-download

const emit = defineEmits<{
  tagCreated: [assetId: number, controlNumber: string]
}>()

/* =========================
   OPEN NEW TAG
========================= */
const openTagModal = async (asset: Asset) => {
  if (asset.asset_code?.control_number) {
    Swal.fire({
      icon: 'info',
      title: 'Tag Already Exists',
      text: `This asset already has a tag: ${asset.asset_code.control_number}. Please use Reprint.`,
      confirmButtonColor: '#2d6b54'
    })
    return
  }

  isReprint.value = false
  await generateTag(asset)
}

/* =========================
   OPEN REPRINT
========================= */
const openReprintModal = async (asset: Asset) => {
  if (!asset.asset_code?.control_number) {
    Swal.fire({
      icon: 'warning',
      title: 'No Tag Found',
      text: 'This asset does not have a control number yet. Please generate a tag first.'
    })
    return
  }

  isReprint.value = true
  await generateTag(asset)
}

/* =========================
   GENERATE TAG
========================= */
const generateTag = async (asset: Asset) => {
  showTagModal.value = true
  isLoading.value = true

  try {
    let assetCode: string

    if (asset.asset_code?.control_number) {
      assetCode = asset.asset_code.control_number
    } else {
      const response = await api.post(`/assets/${asset.id}/generate-code`)
      assetCode = response.data?.unique_code

      if (!assetCode) {
        throw new Error('No control number returned from server')
      }

      emit('tagCreated', asset.id, assetCode)
    }

    taggingAsset.value = { ...asset, uniqueCode: assetCode }

    const qrText = buildQrText(asset, assetCode)

    qrCodeDataUrl.value = await QRCode.toDataURL(qrText, {
      width: 300,
      margin: 2,
      color: { dark: '#000000', light: '#ffffff' }
    })

  } catch (err: any) {
    console.error('generateTag error:', err)
    showTagModal.value = false
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: err?.response?.data?.message ?? 'Failed to generate control number.'
    })
  } finally {
    isLoading.value = false
  }
}

/* =========================
   BUILD QR TEXT HELPER
========================= */
const buildQrText = (asset: Asset, controlNumber: string): string => {
  return (
    `Control Number: ${controlNumber}\n` +
    `Company: ${asset.company?.name ?? 'No Company'}\n` +
    `Department: ${asset.department?.name ?? 'No Department'}\n` +
    `Category: ${asset.category?.name ?? 'No Category'}\n` +
    `Invoice Date: ${asset.invoice_date ?? 'N/A'}\n` +
    `Specification: ${asset.specs ?? 'N/A'}`
  )
}

/* =========================
   CAPTURE captureRef TO BLOB
   Used by both downloadImage
   and autoDownloadTag — always
   captures the real Vue DOM so
   the output is pixel-identical
   to what the modal shows.
========================= */
const captureRefToBlob = async (): Promise<Blob | null> => {
  if (!captureRef.value) return null

  const canvas = await html2canvas(captureRef.value, {
    scale: 2,
    backgroundColor: '#ffffff',
    useCORS: true
  })

  const cmToPixel = 57
  const targetWidth = Math.round(6.4 * cmToPixel)
  const targetHeight = Math.round(3.8 * cmToPixel)

  const resizedCanvas = document.createElement('canvas')
  resizedCanvas.width = targetWidth
  resizedCanvas.height = targetHeight

  const ctx = resizedCanvas.getContext('2d')
  if (ctx) {
    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, targetWidth, targetHeight)
    ctx.drawImage(canvas, 0, 0, targetWidth, targetHeight)
  }

  return new Promise<Blob | null>((resolve) => {
    resizedCanvas.toBlob((blob) => resolve(blob), 'image/png')
  })
}

/* =========================
   SAVE TO BATCH PRINTING
========================= */
const saveToBatch = async (assetId: number, controlNumber: string, blob: Blob) => {
  const formData = new FormData()
  formData.append('asset_id', assetId.toString())
  formData.append('unique_code', controlNumber)
  formData.append('tag_image', blob, `${controlNumber}.png`)

  await api.post('/batch-tags/save', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}

/* =========================
   TRIGGER FILE DOWNLOAD
========================= */
const triggerDownload = (blob: Blob, controlNumber: string) => {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `${controlNumber}.png`
  link.click()
  URL.revokeObjectURL(url)
}

/* =========================
   DOWNLOAD IMAGE
   (called from Print button
   inside the modal)
========================= */
const downloadImage = async () => {
  if (!captureRef.value || !taggingAsset.value?.uniqueCode) return

  try {
    const blob = await captureRefToBlob()
    if (!blob) return

    const controlNumber = taggingAsset.value!.uniqueCode!

    triggerDownload(blob, controlNumber)

    try {
      await saveToBatch(taggingAsset.value!.id, controlNumber, blob)
    } catch (err) {
      console.error('Batch save failed', err)
    }

    await Swal.fire({
      icon: 'success',
      title: isReprint.value ? 'Tag Reprinted!' : 'Tag Generated!',
      timer: 1500,
      showConfirmButton: false
    })

    closeModal()

  } catch (err) {
    console.error(err)
    Swal.fire({
      icon: 'error',
      title: 'Failed to download tag.'
    })
  }
}

/* =========================
   AUTO DOWNLOAD TAG
   Called externally after asset creation:
     tagModalRef.value?.autoDownloadTag(newAsset, controlNumber)

   Strategy: populate the same Vue reactive state
   that the modal template uses, hide the overlay
   visually, wait for nextTick so captureRef renders,
   capture it with html2canvas (pixel-identical to
   what the Print button produces), then clean up.
========================= */
const autoDownloadTag = async (asset: Asset, controlNumber: string) => {
  try {
    // 1. Build QR data URL
    const qrText = buildQrText(asset, controlNumber)
    const generatedQr = await QRCode.toDataURL(qrText, {
      width: 300,
      margin: 2,
      color: { dark: '#000000', light: '#ffffff' }
    })

    // 2. Populate reactive state so template renders captureRef
    taggingAsset.value = { ...asset, uniqueCode: controlNumber }
    qrCodeDataUrl.value = generatedQr
    isLoading.value = false
    // Show modal so captureRef mounts, but keep it visually hidden
    // via the overlay being off-screen (we move it in the template)
    isAutoDownloading.value = true
    showTagModal.value = true

    // 3. Wait for Vue to render captureRef into the DOM
    await nextTick()
    // Extra tick to ensure images inside captureRef have painted
    await new Promise(resolve => setTimeout(resolve, 100))

    // 4. Capture the real rendered captureRef — identical to Print button
    const blob = await captureRefToBlob()

    if (!blob) {
      console.error('autoDownloadTag: blob is null')
      return
    }

    // 5. Download + batch save
    triggerDownload(blob, controlNumber)

    try {
      await saveToBatch(asset.id, controlNumber, blob)
    } catch (err) {
      console.error('Batch save failed', err)
    }

  } catch (err) {
    console.error('autoDownloadTag error:', err)
    Swal.fire({
      icon: 'error',
      title: 'Auto Tag Download Failed',
      text: 'The tag could not be downloaded automatically. Please use the Print Tagging button.',
      confirmButtonColor: '#2d6b54'
    })
  } finally {
    // 6. Clean up all state silently — no modal shown to user
    isAutoDownloading.value = false
    closeModal()
  }
}

/* =========================
   CLOSE MODAL
========================= */
const closeModal = () => {
  showTagModal.value = false
  taggingAsset.value = null
  qrCodeDataUrl.value = ''
  isReprint.value = false
  isLoading.value = false
  isAutoDownloading.value = false
}

defineExpose({ openTagModal, openReprintModal, autoDownloadTag })
</script>

<template>
  <div v-if="showTagModal" class="modal-overlay" :style="isAutoDownloading ? 'opacity:0; pointer-events:none;' : ''" @click.self="closeModal">
    <div class="modal-content">
      <div class="tag-container">

        <div class="tag-header">
          <span class="header-text">
            {{ isReprint ? 'Reprint' : 'Print' }} Tagging
            <span v-if="taggingAsset?.uniqueCode">— {{ taggingAsset.uniqueCode }}</span>
          </span>
          <button @click="closeModal" class="close-btn">✕</button>
        </div>

        <!-- Loading state -->
        <div v-if="isLoading" class="loading-body">
          <p style="text-align:center; color:#2d6b54; font-size:16px;">Generating tag, please wait...</p>
        </div>

        <template v-else>
          <div ref="captureRef" class="tag-body">
            <div class="qr-section">
              <div class="qr-wrapper">
                <img
                  v-if="qrCodeDataUrl"
                  :src="qrCodeDataUrl"
                  alt="QR Code"
                  class="qr-image"
                />
              </div>
              <div class="qr-label">
                {{ taggingAsset?.uniqueCode }}
              </div>
            </div>

            <div class="company-section">
              <div class="company-logo">
                <img
                  v-if="taggingAsset?.company?.logo"
                  :src="taggingAsset.company.logo"
                  alt="Company Logo"
                  class="logo-image"
                  crossorigin="anonymous"
                />
                <div v-else class="logo-placeholder">
                  <div class="placeholder-circle"></div>
                </div>
              </div>

              <div class="company-name">
                {{ taggingAsset?.company?.name ?? 'No Company' }}
              </div>

              <div class="company-code">
                ({{ taggingAsset?.company?.code ?? 'CCP' }})
              </div>
            </div>
          </div>

          <button @click="downloadImage" class="print-btn" :disabled="!taggingAsset?.uniqueCode">
            <span class="plus-icon">⊕</span>
            {{ isReprint ? 'REPRINT' : 'PRINT' }} TAGGING
          </button>
        </template>

      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: transparent;
  border-radius: 12px;
  max-width: 700px;
  width: 90%;
}

.tag-container {
  background: linear-gradient(135deg, #e8f5f0 0%, #d4ebe3 100%);
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.tag-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.header-text {
  font-size: 18px;
  font-weight: 600;
  color: #1a5c4a;
}

.close-btn {
  background: #4a9b7f;
  color: white;
  border: none;
  border-radius: 50%;
  width: 32px;
  height: 32px;
  cursor: pointer;
  font-size: 20px;
}

.loading-body {
  background: white;
  border-radius: 8px;
  padding: 60px 30px;
  margin-bottom: 20px;
  text-align: center;
}

.tag-body {
  background: white;
  border-radius: 8px;
  padding: 30px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 30px;
  margin-bottom: 20px;
}

.qr-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #0d4d3a;
  border-radius: 8px;
  padding: 30px;
}

.qr-wrapper {
  background: white;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 15px;
}

.qr-image {
  width: 200px;
  height: 200px;
}

.qr-label {
  color: white;
  font-size: 28px;
  font-weight: 600;
  text-align: center;
}

.company-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.company-logo {
  width: 180px;
  height: 180px;
  margin-bottom: 20px;
}

.logo-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.placeholder-circle {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  background: linear-gradient(135deg, #7ec9aa, #5ba888);
}

.company-name {
  font-size: 26px;
  font-weight: 700;
  color: #1a5c4a;
  text-align: center;
  word-break: break-word;
}

.company-code {
  font-size: 22px;
  font-weight: 600;
  color: #2d6b54;
  text-align: center;
}

.print-btn {
  width: 100%;
  background: #2d6b54;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 15px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
}

.print-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.print-btn:hover:not(:disabled) {
  background: #235241;
}
</style>