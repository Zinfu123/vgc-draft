import { useMediaQuery } from '@vueuse/core';
import type { Ref } from 'vue';

/**
 * Viewport width at or below which the app sidebar uses a drawer (Sheet).
 * Matches Tailwind's default `md` breakpoint (768px).
 */
export const MOBILE_LAYOUT_MEDIA_QUERY = '(max-width: 768px)' as const;

export function useMobileLayout(): { isMobile: Ref<boolean> } {
    const isMobile = useMediaQuery(MOBILE_LAYOUT_MEDIA_QUERY);

    return { isMobile };
}
