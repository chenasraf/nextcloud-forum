<template>
  <div class="not-found-page">
    <NcEmptyContent :title="title" :description="description">
      <template #icon>
        <component :is="iconComponent" :size="64" />
      </template>
      <template #action>
        <div class="not-found-page__actions">
          <NcButton v-if="showBackButton" @click="goBack">
            <template #icon>
              <ArrowLeftIcon :size="20" />
            </template>
            {{ strings.back }}
          </NcButton>
          <NcButton v-if="showHomeButton" :href="homeUrl" variant="primary">
            <template #icon>
              <HomeIcon :size="20" />
            </template>
            {{ strings.goHome }}
          </NcButton>
        </div>
      </template>
    </NcEmptyContent>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import ArrowLeftIcon from '@icons/ArrowLeft.vue'
import HomeIcon from '@icons/Home.vue'
import AlertCircleIcon from '@icons/AlertCircle.vue'

interface Props {
  title?: string
  description?: string
  icon?: any
  showBackButton?: boolean
  showHomeButton?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: () => t('forum', 'Page not found'),
  description: () => t('forum', 'The page you are looking for could not be found.'),
  icon: AlertCircleIcon,
  showBackButton: true,
  showHomeButton: true,
})

const router = useRouter()

const strings = {
  back: t('forum', 'Back'),
  goHome: t('forum', 'Go to home'),
}

const iconComponent = computed(() => props.icon)
const homeUrl = computed(() => generateUrl('/apps/forum'))

const goBack = () => {
  if (window.history.length > 2) {
    router.back()
  } else {
    router.push('/')
  }
}
</script>

<style scoped lang="scss">
.not-found-page {
	margin-top: 4rem;

	&__actions {
		display: flex;
		gap: 0.5rem;
		align-items: center;
		justify-content: center;
	}
}
</style>
