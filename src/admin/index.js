import { render } from '@wordpress/element';
import ChatApp from './components/Chat/ChatApp';
import '../styles/tokens.css';
import './admin.css';

const root = document.getElementById( 'wp-ai-mind-chat' );
if ( root ) {
    render( <ChatApp />, root );
}
