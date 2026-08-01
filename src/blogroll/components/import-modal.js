/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	Flex,
	FormFileUpload,
	Modal,
	Notice,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

/**
 * Fetch details for imported links, one after the other.
 *
 * @param {Array}    links      Imported links.
 * @param {Function} onProgress Called with the number of finished links.
 * @return {Promise<Array>} Enriched links.
 */
async function enrich( links, onProgress ) {
	const result = [];
	for ( const link of links ) {
		try {
			const found = await apiFetch( {
				path: '/blockroll/v1/discover',
				method: 'POST',
				data: { url: link.url },
			} );
			result.push( {
				...link,
				name: link.name || found.name,
				description: link.description || found.description,
				feedUrl: link.feedUrl || found.feedUrl,
				photo: link.photo || found.photo,
			} );
		} catch {
			result.push( link );
		}
		onProgress( result.length );
	}
	return result;
}

/**
 * Modal to import links from an OPML file.
 *
 * @param {Object}   props          Component props.
 * @param {Function} props.onImport Called with the imported links.
 * @param {Function} props.onClose  Called when the modal closes.
 */
export default function ImportModal( { onImport, onClose } ) {
	const [ text, setText ] = useState( '' );
	const [ url, setUrl ] = useState( '' );
	const [ fetchDetails, setFetchDetails ] = useState( false );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ progress, setProgress ] = useState( null );
	const [ error, setError ] = useState( null );

	const runImport = async () => {
		setIsBusy( true );
		setError( null );
		try {
			const data = text ? { opml: text } : { url };
			const { links } = await apiFetch( {
				path: '/blockroll/v1/import',
				method: 'POST',
				data,
			} );
			const finished = fetchDetails
				? await enrich( links, setProgress )
				: links;
			onImport( finished );
			onClose();
		} catch ( importError ) {
			setError(
				importError.message ||
					__( 'The file could not be imported.', 'blockroll' )
			);
			setIsBusy( false );
			setProgress( null );
		}
	};

	return (
		<Modal
			title={ __( 'Import links', 'blockroll' ) }
			onRequestClose={ onClose }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<p>
				{ __(
					'Import the subscription list of your feed reader (an OPML file).',
					'blockroll'
				) }
			</p>
			<FormFileUpload
				accept=".opml,.xml,text/xml,text/x-opml"
				variant="secondary"
				onChange={ ( event ) => {
					const file = event.target.files?.[ 0 ];
					if ( file ) {
						file.text().then( setText );
					}
				} }
			>
				{ __( 'Choose a file', 'blockroll' ) }
			</FormFileUpload>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Or paste the file content', 'blockroll' ) }
				value={ text }
				onChange={ setText }
			/>
			<TextControl
				__nextHasNoMarginBottom
				label={ __( 'Or enter its address', 'blockroll' ) }
				type="url"
				placeholder="https://example.com/subscriptions.opml"
				value={ url }
				onChange={ setUrl }
				disabled={ !! text }
			/>
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ __( 'Fetch details for imported links', 'blockroll' ) }
				help={ __(
					'Looks up names, descriptions, and images. Takes a moment for long lists.',
					'blockroll'
				) }
				checked={ fetchDetails }
				onChange={ setFetchDetails }
			/>
			<Flex justify="flex-start">
				<Button
					variant="primary"
					disabled={ ( ! text && ! url ) || isBusy }
					onClick={ runImport }
				>
					{ isBusy ? <Spinner /> : __( 'Import', 'blockroll' ) }
				</Button>
				{ null !== progress && (
					<span>
						{ sprintf(
							/* translators: %d: number of links processed. */
							__( '%d links processed…', 'blockroll' ),
							progress
						) }
					</span>
				) }
			</Flex>
		</Modal>
	);
}
