<template>
  <NotFoundPage
    :title="strings.title"
    :description="strings.description"
    :icon="MessageAlertIcon"
    :show-back-button="true"
    :show-home-button="true"
  />
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NotFoundPage from '../components/NotFoundPage.vue'
import MessageAlertIcon from '@icons/MessageAlert.vue'

const strings = {
  title: t('forum', 'Thread not found'),
  description: t('forum', 'The thread you are looking for does not exist or has been removed.'),
}
</script>
