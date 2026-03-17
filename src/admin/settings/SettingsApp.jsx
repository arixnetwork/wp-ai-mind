import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Settings, Key, Mic, Zap } from 'lucide-react';
import ProvidersTab from './ProvidersTab';
import VoiceTab from './VoiceTab';
import FeaturesTab from './FeaturesTab';

const TABS = [
    { id: 'providers', label: 'Providers', Icon: Key },
    { id: 'voice',     label: 'Voice',     Icon: Mic },
    { id: 'features',  label: 'Features',  Icon: Zap },
];

export default function SettingsApp() {
    const [ activeTab,  setActiveTab  ] = useState( 'providers' );
    const [ settings,   setSettings   ] = useState( null );
    const [ isSaving,   setIsSaving   ] = useState( false );
    const [ saveResult, setSaveResult ] = useState( null ); // 'success' | 'error' | null

    useEffect( () => {
        apiFetch( { path: '/wp-ai-mind/v1/settings' } )
            .then( setSettings )
            .catch( console.error );
    }, [] );

    async function saveSettings( patch ) {
        setIsSaving( true );
        setSaveResult( null );
        try {
            await apiFetch( { path: '/wp-ai-mind/v1/settings', method: 'POST', data: patch } );
            setSettings( prev => ( { ...prev, ...patch } ) );
            setSaveResult( 'success' );
        } catch ( e ) {
            setSaveResult( 'error' );
        } finally {
            setIsSaving( false );
        }
    }

    const tabProps = { settings, saveSettings, isSaving };

    return (
        <div className="wpaim-settings-shell">
            <div className="wpaim-settings-header">
                <div className="wpaim-settings-title">
                    <Settings size={ 16 } />
                    <span>WP AI Mind — Settings</span>
                </div>

                { saveResult === 'success' && (
                    <span className="wpaim-settings-notice wpaim-settings-notice--success">
                        Saved successfully
                    </span>
                ) }
                { saveResult === 'error' && (
                    <span className="wpaim-settings-notice wpaim-settings-notice--error">
                        Save failed — please try again
                    </span>
                ) }
            </div>

            <nav className="wpaim-settings-tabs" role="tablist">
                { TABS.map( ( { id, label, Icon } ) => (
                    <button
                        key={ id }
                        role="tab"
                        aria-selected={ activeTab === id }
                        className={ `wpaim-settings-tab${ activeTab === id ? ' is-active' : '' }` }
                        onClick={ () => setActiveTab( id ) }
                    >
                        <Icon size={ 14 } />
                        { label }
                    </button>
                ) ) }
            </nav>

            <div className="wpaim-settings-content" role="tabpanel">
                { settings === null ? (
                    <div className="wpaim-settings-loading">Loading settings…</div>
                ) : (
                    <>
                        { activeTab === 'providers' && <ProvidersTab { ...tabProps } /> }
                        { activeTab === 'voice'     && <VoiceTab     { ...tabProps } /> }
                        { activeTab === 'features'  && <FeaturesTab  { ...tabProps } /> }
                    </>
                ) }
            </div>
        </div>
    );
}
