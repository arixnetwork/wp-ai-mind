import { render } from '@wordpress/element';
import ChatApp from './components/Chat/ChatApp';
import SettingsApp from './settings/SettingsApp';
import '../styles/tokens.css';
import './admin.css';

const chatRoot = document.getElementById( 'wp-ai-mind-chat' );
if ( chatRoot ) {
    render( <ChatApp />, chatRoot );
}

const settingsRoot = document.getElementById( 'wp-ai-mind-settings' );
if ( settingsRoot ) {
    render( <SettingsApp />, settingsRoot );
}
