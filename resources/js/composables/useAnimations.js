import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Observes elements with a given CSS class and adds 'visible'
 * when they scroll into the viewport. Supports staggered delays
 * via data-delay attribute (ms).
 */
export function useScrollReveal(rootSelector = '.anim-enter, .anim-enter-left, .anim-enter-right, .anim-enter-scale') {
  let intersectionObserver = null;
  let mutationObserver = null;

  function observeNew(root) {
    root.querySelectorAll(rootSelector).forEach((el) => {
      if (!el.classList.contains('visible')) {
        intersectionObserver.observe(el);
      }
    });
  }

  onMounted(() => {
    intersectionObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const delay = Number(entry.target.dataset.delay || 0);
            setTimeout(() => entry.target.classList.add('visible'), delay);
            intersectionObserver.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12 }
    );

    // Observe existing elements
    observeNew(document);

    // Watch for new elements added to the DOM (e.g. after v-if toggle)
    mutationObserver = new MutationObserver((mutations) => {
      for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
          if (node.nodeType === Node.ELEMENT_NODE) {
            // Check the node itself and any descendants
            if (node.matches && node.matches(rootSelector) && !node.classList.contains('visible')) {
              intersectionObserver.observe(node);
            }
            if (node.querySelectorAll) {
              observeNew(node);
            }
          }
        }
      }
    });
    mutationObserver.observe(document.body, { childList: true, subtree: true });
  });

  onUnmounted(() => {
    intersectionObserver?.disconnect();
    mutationObserver?.disconnect();
  });
}

/**
 * Animate a number counting up from 0 → target over `duration` ms.
 * Returns a reactive ref with the current displayed value (string).
 */
export function useCountUp(target, duration = 1200, decimals = 0) {
  const display = ref('0');
  let started = false;

  function start() {
    if (started) return;
    started = true;
    const num = typeof target === 'string' ? parseFloat(target.replace(/,/g, '')) : target;
    const startTime = performance.now();

    function tick(now) {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / duration, 1);
      // ease-out quad
      const eased = 1 - (1 - progress) * (1 - progress);
      const current = eased * num;
      display.value = decimals > 0
        ? current.toFixed(decimals)
        : Math.round(current).toLocaleString();
      if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  return { display, start };
}
