<script setup lang="ts">
import { Pie } from 'vue-chartjs'
import type { ChartData, ChartOptions } from 'chart.js'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
} from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

// Props: you can pass your data as a prop
defineProps<{
  data: ChartData<'pie', number[], string>;
  options?: ChartOptions<'pie'>;
}>();
</script>

<template>
  <Pie :data="data" :options="options" />
</template>
