import { useState } from '@wordpress/element';
import { CornerDownLeft, Loader2 } from 'lucide-react';

export default function Composer({ onSend, isLoading }) {
    const [ value, setValue ] = useState( '' );

    function handleKeyDown( e ) {
        if ( e.key === 'Enter' && ! e.shiftKey ) {
            e.preventDefault();
            submit();
        }
    }

    function submit() {
        const text = value.trim();
        if ( ! text || isLoading ) return;
        setValue( '' );
        onSend( text );
    }

    return (
        <div className="wpaim-composer">
            <textarea
                className="wpaim-composer__input"
                placeholder="Ask anything, or describe what you want to create…"
                value={ value }
                rows={ 1 }
                onChange={ e => setValue( e.target.value ) }
                onKeyDown={ handleKeyDown }
                disabled={ isLoading }
            />
            <button
                className="wpaim-btn wpaim-btn--primary wpaim-btn--icon"
                onClick={ submit }
                disabled={ ! value.trim() || isLoading }
                title="Send (Enter)"
            >
                { isLoading
                    ? <Loader2 size={ 14 } strokeWidth={ 1.5 } className="wpaim-spinner" />
                    : <CornerDownLeft size={ 14 } strokeWidth={ 1.5 } />
                }
            </button>
        </div>
    );
}
