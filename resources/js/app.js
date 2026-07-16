import { initNavigation } from './core/navigation.js';
import { initAccessibility } from './core/accessibility.js';
import { initProgress } from './components/progress.js';
import { initConfirmations, initTooltips } from './components/feedback.js';
import { initMotion } from './effects/motion.js';
import { initGlobalSearch } from './components/global-search.js';

document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initAccessibility();
    initProgress();
    initConfirmations();
    initTooltips();
    initMotion();
    initGlobalSearch();
});
