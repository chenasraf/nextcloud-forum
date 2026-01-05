<template>
  <PageWrapper>
    <NotFoundPage
      :title="strings.title"
      :description="strings.description"
      :icon="AlertCircleIcon"
      :show-back-button="true"
      :show-home-button="true"
    />
  </PageWrapper>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NotFoundPage from '@/components/NotFoundPage'
import PageWrapper from '@/components/PageWrapper'
import AlertCircleIcon from '@icons/AlertCircle.vue'

const strings = {
  title: t('forum', 'Page not found'),
  description: t('forum', 'The page you are looking for could not be found.'),
}
</script>
