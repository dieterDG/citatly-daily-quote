import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
	// useBlockProps() liest attributes.className (WordPress-nativ) automatisch
	// und fügt es zu den Block-Wrapper-Klassen hinzu.
	const blockProps = useBlockProps( {
		className: 'citatly citatly--preview',
	} );

	return (
		<div { ...blockProps }>
			<div
				className="citatly__text"
				style={ { whiteSpace: 'pre-line' } }
			>
				{ __( 'Quote of the Day will be displayed here', 'citatly-daily-quote' ) }
			</div>
			<div className="citatly__meta">
				<span className="citatly__author">
					{ __( '— Author', 'citatly-daily-quote' ) }
				</span>
				<span className="citatly__source"></span>
			</div>
		</div>
	);
}
