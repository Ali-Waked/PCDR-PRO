<script setup>
import { computed } from 'vue'
import { useI18n } from '../../composables/useI18n'
import { socialIcons } from './social-icons'

const props = defineProps({
  links: { type: Array, default: () => [] },
  tone: {
    type: String,
    default: 'dark',
    validator: (value) => ['dark', 'light'].includes(value),
  },
  compact: { type: Boolean, default: false },
})

const { site, t } = useI18n()

const organization = computed(() => site.value.shortName || site.value.name || 'PCDR')
const groupLabel = computed(() => t('social_links'))

const visibleLinks = computed(() => props.links
  .filter((link) => link && link.url && link.platform && socialIcons[link.platform])
  .map((link) => ({ ...link, label: link.label || t(`ui.social_platforms.${link.platform}`, link.platform) })))

function linkLabel(link) {
  return `${link.label} — ${organization.value}`
}
</script>

<template>
  <div
    v-if="visibleLinks.length"
    class="ps-social-links"
    :class="[`ps-social-links--${tone}`, { 'ps-social-links--compact': compact }]"
    role="group"
    :aria-label="groupLabel"
  >
    <ul class="ps-social-links__list">
      <li v-for="link in visibleLinks" :key="link.platform" class="ps-social-links__item">
        <a
          class="ps-social-links__link"
          :href="link.url"
          target="_blank"
          rel="noopener noreferrer"
          :aria-label="linkLabel(link)"
        >
          <svg
            class="ps-social-links__icon"
            viewBox="0 0 24 24"
            fill="currentColor"
            fill-rule="evenodd"
            aria-hidden="true"
            focusable="false"
          >
            <path v-for="(path, index) in socialIcons[link.platform].paths" :key="index" :d="path" />
          </svg>
          <span class="ps-social-links__label">{{ link.label }}</span>
        </a>
      </li>
    </ul>
  </div>
</template>
