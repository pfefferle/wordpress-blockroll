/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { Button, Popover } from '@wordpress/components';

/**
 * A button in the card with an overlay under it, like an inline link in
 * a paragraph: the meta row's feed and relationship.
 *
 * @param {Object}         props              Component props.
 * @param {boolean}        props.isOpen       Whether the overlay is open.
 * @param {Function}       props.onToggle     Called to open or close it.
 * @param {string}         props.className    Class of the button.
 * @param {boolean|string} props.focusOnMount What the overlay focuses when it opens.
 * @param {Element}        props.label        Contents of the button.
 * @param {Element}        props.children     Contents of the overlay.
 */
export default function OverlayButton( {
	isOpen,
	onToggle,
	className,
	focusOnMount = 'firstElement',
	label,
	children,
} ) {
	const [ anchor, setAnchor ] = useState();

	return (
		<>
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
					onClose={ onToggle }
					focusOnMount={ focusOnMount }
					className="blockroll-link__overlay"
				>
					<div className="blockroll-link__overlay-more">
						{ children }
					</div>
				</Popover>
			) }
		</>
	);
}
