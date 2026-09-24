/**
 * emcpUI: the shared component library for the EMCP Tools admin.
 * Built as `window.emcpUI`; screens import it as `@emcp/ui`.
 */
import '@fontsource/figtree/latin-400.css';
import '@fontsource/figtree/latin-500.css';
import '@fontsource/figtree/latin-600.css';
import '@fontsource/figtree/latin-700.css';
import '@fontsource/jetbrains-mono/latin-400.css';
import '@fontsource/jetbrains-mono/latin-500.css';
import './styles/tokens.css';
import './styles/base.css';

export const UI_VERSION = '1';
export { COLORS } from './styles/tokens';
export { Icon } from './components/Icon';
export { ICONS } from './icons';
export { cx } from './utils/cx';
