/**
 * WordPress dependencies
 */
import { useEffect, useRef } from '@wordpress/element';

/**
 * A signal for requests that must not outlive the component.
 *
 * The controller is made when the component mounts and aborted when it
 * unmounts, one per mount: the editor's development build mounts twice.
 *
 * @return {Object} A ref whose `current` is the AbortController.
 */
export default function useAbortOnUnmount() {
	const controller = useRef();
	useEffect( () => {
		controller.current = new AbortController();
		return () => controller.current.abort();
	}, [] );
	return controller;
}
