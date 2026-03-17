import { useMemo } from '@wordpress/element';
import { marked } from 'marked';

marked.setOptions( { breaks: true, gfm: true } );

export default function MarkdownContent( { content, className } ) {
	const html = useMemo( () => marked.parse( content || '' ), [ content ] );
	return (
		<div
			className={ className }
			dangerouslySetInnerHTML={ { __html: html } }
		/>
	);
}
