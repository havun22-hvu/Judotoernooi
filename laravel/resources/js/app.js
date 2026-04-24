import Alpine from '@alpinejs/csp';
import collapse from '@alpinejs/collapse';
import { registerAlpineComponents } from './alpine-components';

// Register Alpine plugins
Alpine.plugin(collapse);

// Make Alpine available globally
window.Alpine = Alpine;

// Register CSP-compatible components (replaces inline x-data logic across views)
registerAlpineComponents(Alpine);

// Start Alpine
Alpine.start();
