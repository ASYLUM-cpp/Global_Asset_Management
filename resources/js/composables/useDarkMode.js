import { ref, watch, onMounted } from 'vue';

const isDark = ref(false);

function applyTheme(dark) {
  if (dark) {
    document.documentElement.classList.add('dark');
  } else {
    document.documentElement.classList.remove('dark');
  }
}

export function useDarkMode() {
  onMounted(() => {
    const stored = localStorage.getItem('gam-theme');
    if (stored === 'dark') {
      isDark.value = true;
    } else if (stored === 'light') {
      isDark.value = false;
    } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
      isDark.value = true;
    }
    applyTheme(isDark.value);
  });

  watch(isDark, (val) => {
    localStorage.setItem('gam-theme', val ? 'dark' : 'light');
    applyTheme(val);
  });

  function toggle() {
    isDark.value = !isDark.value;
  }

  return { isDark, toggle };
}
