import { ref } from 'vue'

/** Only one public-header dropdown may be open at a time. */
export const openDropdownId = ref(null)
