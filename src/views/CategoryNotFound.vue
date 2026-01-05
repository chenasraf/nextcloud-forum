<template>
  <NotFoundPage
    :title="strings.title"
    :description="strings.description"
    :icon="FolderAlertIcon"
    :show-back-button="true"
    :show-home-button="true"
  />
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NotFoundPage from '@/components/NotFoundPage'
import FolderAlertIcon from '@icons/FolderAlert.vue'

const strings = {
  title: t('forum', 'Category not found'),
  description: t('forum', 'The category you are looking for does not exist or has been removed.'),
}
</script>
