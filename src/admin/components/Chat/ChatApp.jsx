import { useState, useEffect } from '@wordpress/element';
import { MessageSquare, Plus } from 'lucide-react';
import ConversationHistory from '../Sidebar/ConversationHistory';
import MessageList from './MessageList';
import Composer from './Composer';
import QuickActions from '../RightPanel/QuickActions';
import ModelSelector from '../RightPanel/ModelSelector';
import apiFetch from '@wordpress/api-fetch';

export default function ChatApp() {
    const { isPro } = window.wpAiMindData || {};

    const [ conversations,    setConversations    ] = useState( [] );
    const [ activeConvId,     setActiveConvId     ] = useState( null );
    const [ messages,         setMessages         ] = useState( [] );
    const [ isLoading,        setIsLoading        ] = useState( false );
    const [ selectedProvider, setSelectedProvider ] = useState( '' );
    const [ selectedModel,    setSelectedModel    ] = useState( '' );
    const [ providers,        setProviders        ] = useState( [] );

    useEffect( () => {
        loadConversations();
        loadProviders();
    }, [] );

    useEffect( () => {
        if ( activeConvId ) loadMessages( activeConvId );
    }, [ activeConvId ] );

    async function loadConversations() {
        try {
            const data = await apiFetch( { path: '/wp-ai-mind/v1/conversations' } );
            setConversations( data );
        } catch ( e ) {
            console.error( 'Failed to load conversations', e );
        }
    }

    async function loadProviders() {
        try {
            const data = await apiFetch( { path: '/wp-ai-mind/v1/providers' } );
            setProviders( data );
            if ( data.length > 0 ) setSelectedProvider( data[0].slug );
        } catch ( e ) {
            // Provider list is best-effort — don't crash if unavailable.
        }
    }

    async function loadMessages( convId ) {
        const data = await apiFetch( { path: `/wp-ai-mind/v1/conversations/${convId}/messages` } );
        setMessages( data );
    }

    async function newConversation() {
        const conv = await apiFetch( {
            path:   '/wp-ai-mind/v1/conversations',
            method: 'POST',
            data:   { title: 'New conversation' },
        } );
        setConversations( prev => [ conv, ...prev ] );
        setActiveConvId( conv.id );
        setMessages( [] );
    }

    async function sendMessage( content ) {
        // Resolve conversation ID — create one if none active.
        let convId = activeConvId;
        if ( ! convId ) {
            const conv = await apiFetch( {
                path:   '/wp-ai-mind/v1/conversations',
                method: 'POST',
                data:   { title: content.slice( 0, 60 ) },
            } );
            setConversations( prev => [ conv, ...prev ] );
            setActiveConvId( conv.id );
            convId = conv.id; // capture new ID — do NOT use activeConvId (stale closure)
        }

        setMessages( prev => [ ...prev, { role: 'user', content } ] );
        setIsLoading( true );

        try {
            const res = await apiFetch( {
                path:   `/wp-ai-mind/v1/conversations/${convId}/messages`,
                method: 'POST',
                data:   { content, provider: selectedProvider, model: selectedModel },
            } );
            setMessages( prev => [ ...prev, { role: 'assistant', content: res.content, model: res.model, tokens: res.tokens } ] );
        } finally {
            setIsLoading( false );
        }
    }

    return (
        <div className="wpaim-shell">
            <aside className="wpaim-sidebar">
                <div className="wpaim-sidebar__header">
                    <span className="wpaim-sidebar__title">Conversations</span>
                    <button
                        className="wpaim-btn wpaim-btn--ghost wpaim-btn--icon"
                        onClick={ newConversation }
                        title="New conversation"
                    >
                        <Plus size={ 14 } strokeWidth={ 1.5 } />
                    </button>
                </div>
                <ConversationHistory
                    conversations={ conversations }
                    activeId={ activeConvId }
                    onSelect={ setActiveConvId }
                />
            </aside>

            <main className="wpaim-main">
                { messages.length === 0 && ! isLoading
                    ? <EmptyState />
                    : <MessageList messages={ messages } isLoading={ isLoading } />
                }
                <Composer onSend={ sendMessage } isLoading={ isLoading } />
            </main>

            <aside className="wpaim-right-panel">
                <ModelSelector
                    providers={ providers }
                    selectedProvider={ selectedProvider }
                    selectedModel={ selectedModel }
                    onProviderChange={ setSelectedProvider }
                    onModelChange={ setSelectedModel }
                />
                <QuickActions onAction={ sendMessage } isPro={ isPro } />
            </aside>
        </div>
    );
}

function EmptyState() {
    return (
        <div className="wpaim-empty">
            <MessageSquare size={ 32 } strokeWidth={ 1 } className="wpaim-empty__icon" />
            <p className="wpaim-empty__title">What would you like to work on?</p>
            <p className="wpaim-empty__subtitle">Ask anything, or choose a quick action on the right.</p>
        </div>
    );
}
