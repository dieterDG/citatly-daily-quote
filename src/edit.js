import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
	// useBlockProps() liest attributes.className (WordPress-nativ) automatisch
	// und fügt es zu den Block-Wrapper-Klassen hinzu.
	const blockProps = useBlockProps( {
		className: 'qotd qotd--preview',
	} );

	return (
		<div { ...blockProps }>
			<div
				className="qotd__text"
				style={ { whiteSpace: 'pre-line' } }
			>
				{ __( 'Zitat des Tages wird hier angezeigt', 'qotd' ) }
			</div>
			<div className="qotd__meta">
				<span className="qotd__author">
					{ __( '— Autor', 'qotd' ) }
				</span>
				<span className="qotd__source"></span>
			</div>
		</div>
	);
}
