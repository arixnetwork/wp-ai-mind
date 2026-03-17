import { useState } from '@wordpress/element';
import { Key, Link } from 'lucide-react';

const API_KEY_PROVIDERS = [
    { id: 'claude',  label: 'Claude (Anthropic)' },
    { id: 'openai',  label: 'OpenAI' },
    { id: 'gemini',  label: 'Google Gemini' },
];

const PROVIDER_OPTIONS = [
    { value: 'claude',  label: 'Claude' },
    { value: 'openai',  label: 'OpenAI' },
    { value: 'gemini',  label: 'Gemini' },
    { value: 'ollama',  label: 'Ollama (local)' },
];

export default function ProvidersTab( { settings, saveSettings, isSaving } ) {
    const apiKeys       = settings?.api_keys       ?? {};
    const [ dirty, setDirty ] = useState( {} ); // { [provider]: string }

    function handleKeyChange( provider, value ) {
        setDirty( prev => ( { ...prev, [ provider ]: value } ) );
    }

    function handleSaveKey( provider ) {
        const value = dirty[ provider ] ?? '';
        saveSettings( { api_keys: { ...apiKeys, [ provider ]: value } } );
        setDirty( prev => {
            const next = { ...prev };
            delete next[ provider ];
            return next;
        } );
    }

    function handleUrlChange( value ) {
        setDirty( prev => ( { ...prev, ollama_url: value } ) );
    }

    function handleSaveUrl() {
        const value = dirty.ollama_url ?? '';
        saveSettings( { api_keys: { ...apiKeys, ollama_url: value } } );
        setDirty( prev => {
            const next = { ...prev };
            delete next.ollama_url;
            return next;
        } );
    }

    return (
        <div className="wpaim-providers-tab">

            {/* Default & image provider selects */ }
            <section className="wpaim-settings-section">
                <h3 className="wpaim-settings-section-title">Default Providers</h3>

                <div className="wpaim-field-row">
                    <label className="wpaim-field-label" htmlFor="wpaim-default-provider">
                        Default AI Provider
                    </label>
                    <select
                        id="wpaim-default-provider"
                        className="wpaim-select"
                        value={ settings?.default_provider ?? '' }
                        onChange={ e => saveSettings( { default_provider: e.target.value } ) }
                    >
                        { PROVIDER_OPTIONS.map( o => (
                            <option key={ o.value } value={ o.value }>{ o.label }</option>
                        ) ) }
                    </select>
                </div>

                <div className="wpaim-field-row">
                    <label className="wpaim-field-label" htmlFor="wpaim-image-provider">
                        Image Provider
                    </label>
                    <select
                        id="wpaim-image-provider"
                        className="wpaim-select"
                        value={ settings?.image_provider ?? '' }
                        onChange={ e => saveSettings( { image_provider: e.target.value } ) }
                    >
                        <option value="openai">OpenAI (DALL·E)</option>
                        <option value="gemini">Google Gemini</option>
                    </select>
                </div>
            </section>

            {/* API key inputs */ }
            <section className="wpaim-settings-section">
                <h3 className="wpaim-settings-section-title">API Keys</h3>

                { API_KEY_PROVIDERS.map( ( { id, label } ) => (
                    <div key={ id } className="wpaim-field-row wpaim-field-row--key">
                        <label className="wpaim-field-label" htmlFor={ `wpaim-key-${ id }` }>
                            <Key size={ 13 } />
                            { label }
                        </label>
                        <div className="wpaim-field-input-group">
                            <input
                                id={ `wpaim-key-${ id }` }
                                type="password"
                                className="wpaim-input"
                                value={ dirty[ id ] ?? '' }
                                placeholder={ apiKeys[ id ] ? '••••••••••••' : 'Enter API key…' }
                                onChange={ e => handleKeyChange( id, e.target.value ) }
                                autoComplete="new-password"
                            />
                            <button
                                className="wpaim-btn wpaim-btn--primary"
                                disabled={ isSaving || dirty[ id ] === undefined }
                                onClick={ () => handleSaveKey( id ) }
                            >
                                { isSaving ? 'Saving…' : 'Save' }
                            </button>
                        </div>
                    </div>
                ) ) }
            </section>

            {/* Ollama URL */ }
            <section className="wpaim-settings-section">
                <h3 className="wpaim-settings-section-title">Ollama (Self-hosted)</h3>

                <div className="wpaim-field-row wpaim-field-row--key">
                    <label className="wpaim-field-label" htmlFor="wpaim-ollama-url">
                        <Link size={ 13 } />
                        Ollama URL
                    </label>
                    <div className="wpaim-field-input-group">
                        <input
                            id="wpaim-ollama-url"
                            type="url"
                            className="wpaim-input"
                            value={ dirty.ollama_url ?? '' }
                            placeholder={ apiKeys.ollama_url ?? 'http://localhost:11434' }
                            onChange={ e => handleUrlChange( e.target.value ) }
                        />
                        <button
                            className="wpaim-btn wpaim-btn--primary"
                            disabled={ isSaving || dirty.ollama_url === undefined }
                            onClick={ handleSaveUrl }
                        >
                            { isSaving ? 'Saving…' : 'Save' }
                        </button>
                    </div>
                </div>
            </section>
        </div>
    );
}
