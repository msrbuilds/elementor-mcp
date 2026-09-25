import { ICONS } from '../icons';
import { cx } from '../utils/cx';

/**
 * A Lucide icon by name. Decorative (aria-hidden) unless `label` is given.
 *
 * @param {Object} props
 * @param {string} props.name        Kebab-case icon name from icon-names.json.
 * @param {number} [props.size]      Pixel size, default 16.
 * @param {string} [props.label]     Accessible name; makes the icon an image.
 * @param {string} [props.className] Extra classes.
 */
export function Icon( { name, size = 16, label, className } ) {
	const Cmp = ICONS[ name ];
	if ( ! Cmp ) {
		return null;
	}
	const a11y = label
		? { role: 'img', 'aria-label': label }
		: { 'aria-hidden': 'true', focusable: 'false' };
	return (
		<Cmp
			size={ size }
			strokeWidth={ 2 }
			className={ cx( 'eui-icon', className ) }
			{ ...a11y }
		/>
	);
}
