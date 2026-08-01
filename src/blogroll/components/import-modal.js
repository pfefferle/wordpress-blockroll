/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { upload } from '@wordpress/icons';
import {
	Button,
	CheckboxControl,
	DropZone,
	FormFileUpload,
	Modal,
	Notice,
	TabPanel,
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
	const [ fileName, setFileName ] = useState( '' );
	const [ url, setUrl ] = useState( '' );
	const [ fetchDetails, setFetchDetails ] = useState( false );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ progress, setProgress ] = useState( null );
	const [ error, setError ] = useState( null );

	const readFile = ( file ) => {
		if ( file ) {
			file.text().then( setText );
			setFileName( file.name );
		}
	};

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

	const tabs = {
		file: (
			<div className="blockroll-import-upload">
				<DropZone onFilesDrop={ ( files ) => readFile( files[ 0 ] ) } />
				<FormFileUpload
					__next40pxDefaultSize
					accept=".opml,.xml,text/xml,text/x-opml"
					icon={ upload }
					variant="secondary"
					onChange={ ( event ) =>
						readFile( event.target.files?.[ 0 ] )
					}
				>
					{ fileName || __( 'Choose or drop a file', 'blockroll' ) }
				</FormFileUpload>
			</div>
		),
		paste: (
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'File content', 'blockroll' ) }
				hideLabelFromVision
				rows={ 6 }
				value={ text }
				onChange={ ( value ) => {
					setText( value );
					setFileName( '' );
				} }
			/>
		),
		url: (
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Address', 'blockroll' ) }
				hideLabelFromVision
				type="url"
				placeholder="https://example.com/subscriptions.opml"
				value={ url }
				onChange={ setUrl }
			/>
		),
	};

	return (
		<Modal
			title={ __( 'Import links', 'blockroll' ) }
			size="medium"
			onRequestClose={ onClose }
		>
			<div className="blockroll-form">
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				<p className="blockroll-import-intro">
					{ __(
						'Import the subscription list of your feed reader (an OPML file).',
						'blockroll'
					) }
				</p>
				<TabPanel
					tabs={ [
						{
							name: 'file',
							title: __( 'Upload', 'blockroll' ),
						},
						{
							name: 'paste',
							title: __( 'Paste', 'blockroll' ),
						},
						{
							name: 'url',
							title: __( 'Address', 'blockroll' ),
						},
					] }
				>
					{ ( tab ) => (
						<div className="blockroll-import-tab">
							{ tabs[ tab.name ] }
						</div>
					) }
				</TabPanel>
				<CheckboxControl
					__nextHasNoMarginBottom
					label={ __(
						'Fetch details for imported links',
						'blockroll'
					) }
					help={ __(
						'Looks up names, descriptions, and images. Takes a moment for long lists.',
						'blockroll'
					) }
					checked={ fetchDetails }
					onChange={ setFetchDetails }
				/>
				<div className="blockroll-form__footer">
					{ null !== progress && (
						<span>
							{ sprintf(
								/* translators: %d: number of links processed. */
								__( '%d links processed…', 'blockroll' ),
								progress
							) }
						</span>
					) }
					<Button
						__next40pxDefaultSize
						variant="tertiary"
						onClick={ onClose }
					>
						{ __( 'Cancel', 'blockroll' ) }
					</Button>
					<Button
						__next40pxDefaultSize
						variant="primary"
						isBusy={ isBusy }
						disabled={ ( ! text && ! url ) || isBusy }
						onClick={ runImport }
					>
						{ __( 'Import', 'blockroll' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
