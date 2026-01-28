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


  //  FETCH TAGS

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


  //  SOFT DELETE

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


  //  PRINT + MARK AS PRINTED

const printAll = async () => {
  const win = window.open('', '_blank')
  if (!win) return

  win.document.write(`
    <html>
      <head>
        <title>Batch Tags</title>
        <style>
          @page { size: A4; margin: 5mm; }

          body {
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 4mm;
          }

          .tag {
            width: 241px;  /* 64mm */
            height: 143px; /* 38mm */
            outline: 1px dashed black;
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

  batchTags.value.forEach(tag => {
    win.document.write(`
      <div class="tag">
        <img src="${tag.url}" />
      </div>
    `)
  })

  win.document.write('</body></html>')
  win.document.close()
  win.focus()
  win.print()
  win.close()

  // Mark each tag as printed after printing
  try {
    await Promise.all(
      batchTags.value.map(tag =>
        api.post(`/batch-tags/${tag.id}/mark-printed`)
      )
    )
    batchTags.value.forEach(t => (t.print_status = 'printed'))
  } catch (err) {
    console.error('Failed to update print status', err)
  }
}

onMounted(fetchBatchTags)
</script>

<template>
  <NavBar />

  <div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-2xl font-bold">Batch Printing Tags</h2>
      <button class="print-btn" @click="printAll">Print All</button>
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

        <!-- STATUS -->
        <span
          class="status-badge"
          :class="tag.print_status"
        >
          {{ tag.print_status === 'printed' ? 'Printed' : 'Not Printed' }}
        </span>

        <button
          class="delete-btn mt-3"
          @click="softDeleteTag(tag)"
        >
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

.tag {
  width: 64mm;           
  height: 38mm;         
  border: 1px dashed black; 
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  page-break-inside: avoid; /* No splitting tags across pages */
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
