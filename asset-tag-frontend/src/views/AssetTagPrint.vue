<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import NavBar from '@/components/NavBar.vue'
import Swal from 'sweetalert2'

interface BatchTag {
  id: number
  asset_id: number
  unique_code: string
  file_path: string
  url: string
  print_status: 'printed' | 'not_printed'
}

const batchTags = ref<BatchTag[]>([])
const loading = ref(false)

// FETCH TAGS
const fetchBatchTags = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/batch-tags')
    batchTags.value = data
  } catch (err) {
    console.error(err)
    Swal.fire('Error', 'Failed to load batch tags', 'error')
  } finally {
    loading.value = false
  }
}

// SOFT DELETE
const softDeleteTag = async (tag: BatchTag) => {
  const result = await Swal.fire({
    title: 'Delete tag?',
    text: tag.unique_code,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete'
  })

  if (!result.isConfirmed) return

  try {
    await api.delete(`/batch-tags/${tag.id}`)
    batchTags.value = batchTags.value.filter(t => t.id !== tag.id)
    Swal.fire('Deleted', '', 'success')
  } catch (err) {
    console.error(err)
    Swal.fire('Error', 'Delete failed', 'error')
  }
}

// CONVERT IMAGE URL TO BLOB URL (faster than base64)
const toBase64 = (url: string): Promise<string> => {
  return fetch(url)
    .then(res => res.blob())
    .then(blob => URL.createObjectURL(blob))
    .catch(() => url) // fallback to original URL if conversion fails
}
  
// PRINT + MARK AS PRINTED (only unprinted tags)
const printAll = async () => {
  // ✅ Only print tags that are not yet printed
  const unprintedTags = batchTags.value.filter(tag => tag.print_status === 'not_printed')

  if (unprintedTags.length === 0) {
    Swal.fire('Nothing to Print', 'All tags have already been printed.', 'info')
    return
  }

  const tagsWithBase64 = await Promise.all(
    unprintedTags.map(async tag => ({
      ...tag,
      base64Url: await toBase64(tag.url)
    }))
  )

  // ✅ Open window after conversion
  const win = window.open('', '_blank')
  if (!win) {
    Swal.fire('Popup Blocked', 'Please allow popups for this site and try again.', 'warning')
    return
  }

  win.document.write(`
    <html>
      <head>
        <title>Batch Tags</title>
        <style>
          @page {
            size: A4;
            margin: 0;
          }

          body {
            margin: 5mm;
            display: grid;
            grid-template-columns: repeat(3, 64mm);
            grid-auto-rows: 38mm;
            gap: 4mm;
            justify-content: center;
          }

          .tag {
            width: 64mm;
            height: 38mm;
            border: 0.5mm dashed black;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            page-break-inside: avoid;
          }

          .tag img {
            width: 100%;
            height: 100%;
            object-fit: contain;
          }
        </style>
      </head>
      <body>
  `)

  tagsWithBase64.forEach(tag => {
    win.document.write(`
      <div class="tag">
        <img src="${tag.base64Url}" />
      </div>
    `)
  })

  win.document.write('</body></html>')
  win.document.close()

  // ✅ Images are blob URLs so they load instantly — print immediately on load
  win.onload = () => {
    win.focus()
    win.print()
    win.close()
  }

  // ✅ Only mark the unprinted tags as printed
  try {
    await Promise.all(
      unprintedTags.map(tag =>
        api.post(`/batch-tags/${tag.id}/mark-printed`)
      )
    )
    unprintedTags.forEach(tag => {
      const found = batchTags.value.find(t => t.id === tag.id)
      if (found) found.print_status = 'printed'
    })
  } catch (err) {
    console.error('Failed to update print status', err)
  }
}

// DELETE ALL PRINTED
const confirmDeleteAllPrinted = () => {
  Swal.fire({
    title: 'Delete all printed tags?',
    text: 'This will delete all printed records.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Yes, delete all'
  }).then(result => {
    if (result.isConfirmed) {
      deleteAllPrinted()
    }
  })
}

const deleteAllPrinted = async () => {
  try {
    await api.delete('/batch-tags/delete-printed')
    batchTags.value = batchTags.value.filter(tag => tag.print_status !== 'printed')
    Swal.fire('Deleted', 'All printed tags were deleted', 'success')
  } catch (err) {
    console.error(err)
    Swal.fire('Error', 'Failed to delete printed tags', 'error')
  }
}

onMounted(fetchBatchTags)
</script>

<template>
  <NavBar />

  <div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-2xl font-bold">Batch Printing Tags</h2>
      <div class="flex gap-3">
        <button class="print-btn" @click="printAll">Print All</button>
        <button class="delete-all-btn" @click="confirmDeleteAllPrinted">Delete All Printed</button>
      </div>
    </div>

    <div v-if="loading" class="text-center text-gray-500">
      Loading...
    </div>

    <div v-else class="grid grid-cols-3 gap-4">
      <div v-for="tag in batchTags" :key="tag.id" class="tag-card">
        <img :src="tag.url" class="tag-image" />
        <div class="font-semibold mt-2">
          {{ tag.unique_code }}
        </div>

        <span class="status-badge" :class="tag.print_status">
          {{ tag.print_status === 'printed' ? 'Printed' : 'Not Printed' }}
        </span>

        <button class="delete-btn mt-3" @click="softDeleteTag(tag)">
          🗑 Delete
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.print-btn {
  background: #2d6b54;
  color: white;
  padding: 10px 20px;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  border: none;
}

.print-btn:hover {
  background: #235241;
}

.delete-all-btn {
  background: #dc2626;
  color: white;
  padding: 10px 20px;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  border: none;
}

.delete-all-btn:hover {
  background: #b91c1c;
}

.tag-card {
  background: white;
  border-radius: 8px;
  padding: 12px;
  text-align: center;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.tag-image {
  width: 100%;
  height: 120px;
  object-fit: contain;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.status-badge {
  display: inline-block;
  margin-top: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}

.status-badge.not_printed {
  background: #fdecea;
  color: #c0392b;
}

.status-badge.printed {
  background: #eafaf1;
  color: #1e8449;
}

.delete-btn {
  background: #e74c3c;
  color: white;
  padding: 6px 12px;
  border-radius: 4px;
  border: none;
  cursor: pointer;
  font-weight: 600;
}

.delete-btn:hover {
  background: #c0392b;
}
</style>