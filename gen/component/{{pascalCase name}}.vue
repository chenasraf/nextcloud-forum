<template>
  <div>{{ startCase name }}</div>
</template>

<script lang="ts">
import { defineComopnent, type PropType } from 'vue'
// import NcComponentExample from '@nextcloud/vue/components/NcComponentExample'

// import IconExample from '@icons/Example.vue'

export default defineComponent({
  name: '{{pascalCase name}}',
  components: {
    //
  },
})
</script>

<style scoped lang="scss"></style>
