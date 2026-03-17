import { Cpu } from 'lucide-react';

export default function ModelSelector({ providers, selectedProvider, selectedModel, onProviderChange, onModelChange }) {
    const active = providers.find( p => p.slug === selectedProvider );
    const models = active ? Object.entries( active.models ) : [];

    return (
        <div className="wpaim-panel-section">
            <div className="wpaim-panel-label">Model</div>
            <div className="wpaim-model-selector">
                <div className="wpaim-model-selector__row">
                    <Cpu size={ 12 } strokeWidth={ 1.5 } />
                    <select
                        className="wpaim-select"
                        value={ selectedProvider }
                        onChange={ e => { onProviderChange( e.target.value ); onModelChange( '' ); } }
                    >
                        { providers.map( p => (
                            <option key={ p.slug } value={ p.slug }>
                                { p.slug.charAt(0).toUpperCase() + p.slug.slice(1) }
                            </option>
                        ) ) }
                    </select>
                </div>
                { models.length > 0 && (
                    <select
                        className="wpaim-select wpaim-select--sm"
                        value={ selectedModel }
                        onChange={ e => onModelChange( e.target.value ) }
                    >
                        <option value="">Default model</option>
                        { models.map( ([ id, label ]) => (
                            <option key={ id } value={ id }>{ label }</option>
                        ) ) }
                    </select>
                ) }
            </div>
        </div>
    );
}
