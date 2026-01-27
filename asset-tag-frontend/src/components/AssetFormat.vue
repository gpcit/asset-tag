<script setup lang="ts">
import { ref } from 'vue'
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
    code: string
    logo?: string
  }
  person_in_charge?: string
  department?: string
  specs?: string
  asset_code?: { unique_code: string }
}

const showTagModal = ref(false)
const taggingAsset = ref<Asset | null>(null)
const qrCodeDataUrl = ref('')
const captureRef = ref<HTMLElement | null>(null)
const isReprint = ref(false)

const emit = defineEmits<{
  tagCreated: [assetId: number, uniqueCode: string]
}>()

const openTagModal = async (asset: Asset) => {
  const existingCode = asset.asset_code?.unique_code
  
  if (existingCode) {
    Swal.fire({
      icon: 'info',
      title: 'Tag Already Exists',
      text: `This asset already has a tag: ${existingCode}. Please use the "Reprint Tag" option in the Asset Tag page.`,
      confirmButtonColor: '#2d6b54'
    })
    return
  }
  
  isReprint.value = false
  await generateTag(asset)
}

const openReprintModal = async (asset: Asset) => {
  isReprint.value = true
  await generateTag(asset)
}

const generateTag = async (asset: Asset) => {
  showTagModal.value = true
  
  try {
    const existingCode = asset.asset_code?.unique_code
    
    let assetCode: string
    
    if (existingCode) {
      assetCode = existingCode
    } else {
      const companyCode = asset.company?.code?.trim() || 'NO-CODE'
      const categoryPrefix = asset.category?.name 
        ? asset.category.name.substring(0, 3).toUpperCase() 
        : 'AST'
      const uniqueNumber = asset.id.toString().padStart(6, '0')
      assetCode = `${companyCode}-${categoryPrefix}${uniqueNumber}`
    }
    
    taggingAsset.value = { ...asset, uniqueCode: assetCode }
    
    // Change this part to include all information
    const qrText = 
      `Unique Code: ${assetCode}\n` +
      `Company: ${asset.company?.name ?? 'No Company'}\n` +
      `Category: ${asset.category?.name ?? 'No Category'}\n` +
      `Person In-charge: ${asset.person_in_charge ?? 'Unknown'}\n` +
      `Department: ${asset.department ?? 'N/A'}`
    
    qrCodeDataUrl.value = await QRCode.toDataURL(qrText, {
      width: 300,
      margin: 2,
      color: {
        dark: '#000000',
        light: '#ffffff'
      }
    })
  } catch (err) {
    console.error('QR generation failed', err)
    qrCodeDataUrl.value = ''
  }
}

const downloadImage = async () => {
  if (!captureRef.value || !taggingAsset.value || !taggingAsset.value.uniqueCode) return
  
  try {
    const canvas = await html2canvas(captureRef.value, { 
      scale: 2, 
      backgroundColor: '#ffffff'
    })
    
    const targetWidthCm = 6.4
    const targetHeightCm = 3.8
    const cmToPixel = 55 // comeback to this need adjusting
    
    const targetWidth = Math.round(targetWidthCm * cmToPixel)
    const targetHeight = Math.round(targetHeightCm * cmToPixel)
    
    const resizedCanvas = document.createElement('canvas')
    resizedCanvas.width = targetWidth
    resizedCanvas.height = targetHeight
    
    const ctx = resizedCanvas.getContext('2d')
    if (ctx) {
      ctx.fillStyle = '#ffffff'
      ctx.fillRect(0, 0, targetWidth, targetHeight)
      ctx.drawImage(canvas, 0, 0, targetWidth, targetHeight)
    }
    
    resizedCanvas.toBlob(async (blob) => {
      if (!blob) return
      
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      const uniqueCode = taggingAsset.value!.uniqueCode!
      link.href = url
      link.download = `${uniqueCode}.png`
      link.click()
      URL.revokeObjectURL(url)
      
      if (!isReprint.value) {
        await api.post('/assets/unique-code', {
          asset_id: taggingAsset.value!.id,
          unique_code: uniqueCode,
        })
        emit('tagCreated', taggingAsset.value!.id, uniqueCode)
      }
      
      Swal.fire({ 
        icon: 'success', 
        title: isReprint.value ? 'Tag Reprinted!' : 'Downloaded & Unique Code Saved!', 
        timer: 1500, 
        showConfirmButton: false 
      })
      
      closeModal()
    }, 'image/png')
    
  } catch (err) {
    console.error('Error capturing or saving:', err)
    Swal.fire({ 
      icon: 'error', 
      title: 'Failed to download or save unique code.' 
    })
  }
}
const closeModal = () => {
  showTagModal.value = false
  taggingAsset.value = null
  qrCodeDataUrl.value = ''
  isReprint.value = false
}

defineExpose({ openTagModal, openReprintModal })
</script>

<template>
  <div v-if="showTagModal" class="modal-overlay" @click.self="closeModal">
    <div class="modal-content">
      <div class="tag-container">
        <div class="tag-header">
          <span class="header-text">{{ isReprint ? 'Reprint' : 'Print' }} Tagging {{ taggingAsset?.uniqueCode }}</span>
          <button @click="closeModal" class="close-btn">✕</button>
        </div>

        <div ref="captureRef" class="tag-body">
          <div class="qr-section">
            <div class="qr-wrapper">
              <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="QR Code" class="qr-image" />
            </div>
            <div class="qr-label">{{ taggingAsset?.uniqueCode }}</div>
          </div>

          <div class="company-section">
            <div class="company-logo">
              <img 
                v-if="taggingAsset?.company?.logo" 
                :src="taggingAsset.company.logo" 
                alt="Company Logo" 
                class="logo-image"
              />
              <div v-else class="logo-placeholder">
                <div class="placeholder-circle"></div>
              </div>
            </div>
            <div class="company-name">
              {{ taggingAsset?.company?.name ?? 'No Company' }}
            </div>
            <div class="company-code">
              - ({{ taggingAsset?.company?.code ?? 'CCP' }})
            </div>
          </div>
        </div>

        <button @click="downloadImage" class="print-btn">
          <span class="plus-icon">⊕</span> {{ isReprint ? 'REPRINT' : 'PRINT' }} TAGGING
        </button>
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
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 20px;
  transition: background 0.3s;
}

.close-btn:hover {
  background: #3d8268;
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
  display: block;
  width: 200px;
  height: 200px;
}

.qr-label {
  color: white;
  font-size: 16px;
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

.logo-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.placeholder-circle {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  background: linear-gradient(135deg, #7ec9aa 0%, #5ba888 50%, #8b7355 100%);
  box-shadow: inset 0 -20px 30px rgba(0, 0, 0, 0.2);
}

.company-name {
  font-size: 20px;
  font-weight: 700;
  color: #1a5c4a;
  margin-bottom: 5px;
}

.company-code {
  font-size: 18px;
  font-weight: 600;
  color: #2d6b54;
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
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: background 0.3s;
}

.print-btn:hover {
  background: #235241;
}

.plus-icon {
  font-size: 20px;
}
</style>