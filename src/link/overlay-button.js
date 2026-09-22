/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { Button, Popover } from '@wordpress/components';
import { __experimentalUseFocusOutside as useFocusOutside } from '@wordpress/compose';

/**
 * Nothing: the overlay's own focus check is off, the wrapper does it.
 */
const noop = () => {};

/**
 * A button in the card with an overlay under it, like an inline link in
 * a paragraph: the meta row's feed and relationship. The overlay closes
 * when the focus leaves the button and the overlay; a click on the
 * button itself only toggles.
 *
 * @param {Object}         props              Component props.
 * @param {boolean}        props.isOpen       Whether the overlay is open.
 * @param {Function}       props.onToggle     Called to open or close it.
 * @param {Function}       props.onClose      Called to close it.
 * @param {string}         props.className    Class of the button.
 * @param {boolean|string} props.focusOnMount What the overlay focuses when it opens.
 * @param {Element}        props.label        Contents of the button.
 * @param {Element}        props.children     Contents of the overlay.
 */
export default function OverlayButton( {
	isOpen,
	onToggle,
	onClose,
	className,
	focusOnMount = 'firstElement',
	label,
	children,
} ) {
	const [ anchor, setAnchor ] = useState();
	const focusOutside = useFocusOutside( onClose );

	return (
		<span { ...focusOutside }>
			<Button
				className={ className }
				ref={ setAnchor }
				aria-expanded={ isOpen }
				onClick={ onToggle }
			>
				{ label }
			</Button>
			{ isOpen && (
				<Popover
					anchor={ anchor }
					placement="bottom-start"
					shift
					onClose={ onClose }
					onFocusOutside={ noop }
					focusOnMount={ focusOnMount }
					className="blockroll-link__meta-overlay"
				>
					{ children }
				</Popover>
			) }
		</span>
	);
}
