import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

// Register Alpine plugins
Alpine.plugin(collapse);

// Make Alpine available globally
window.Alpine = Alpine;

// CSP-migration: shared Alpine.data() registrations. Must run before Alpine.start().
import './alpine-components';

// Start Alpine
Alpine.start();
