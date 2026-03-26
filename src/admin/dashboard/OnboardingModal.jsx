import { useState, useCallback } from '@wordpress/element';

const PROVIDERS = [
    {
        id: 'openai',
        name: 'OpenAI',
        keyUrl: 'https://platform.openai.com/api-keys',
        keyLabel: 'Get API key',
        placeholder: 'sk-…  Paste your OpenAI API key',
    },
    {
        id: 'claude',
        name: 'Claude',
        keyUrl: 'https://console.anthropic.com/settings/keys',
        keyLabel: 'Get API key',
        placeholder: 'sk-ant-…  Paste your Anthropic API key',
    },
    {
        id: 'gemini',
        name: 'Gemini',
        keyUrl: 'https://aistudio.google.com/apikey',
        keyLabel: 'Get API key',
        placeholder: 'AI…  Paste your Gemini API key',
    },
];

const IMAGE_PROVIDERS = [
    {
        id: 'openai',
        name: 'OpenAI (DALL·E)',
        docsUrl: 'https://platform.openai.com/docs/guides/images',
        docsLabel: 'DALL·E docs',
    },
    {
        id: 'gemini',
        name: 'Gemini (Imagen 3)',
        docsUrl: 'https://ai.google.dev/gemini-api/docs/image-generation',
        docsLabel: 'Imagen docs',
        // DEV NOTE: Verify Imagen 3 endpoint before shipping.
        // Confirm if imagen-3.0-generate-* uses generativelanguage.googleapis.com
        // (same as text) or requires Vertex AI / a separate endpoint.
    },
];

// ── Step 1 ────────────────────────────────────────────────────────────────────

function Step1( { selection, onSelect, onContinue, onSkip, upgradeUrl } ) {
    const choices = [
        {
            id: 'plugin',
            title: 'Use Plugin API',
            badge: 'Free',
            desc: 'Start immediately. Built-in access with usage limits. No API key needed.',
        },
        {
            id: 'own_key',
            title: 'Use my own API key',
            badge: null,
            desc: 'Connect OpenAI, Claude, or Gemini directly. Unlimited usage.',
        },
        {
            id: 'pro',
            title: 'Upgrade to Pro',
            badge: 'Pro',
            desc: 'Full access, priority support, and advanced features.',
        },
    ];

    const isPro = selection === 'pro';

    return (
        <>
            <div className="wpaim-ob-header">
                <div className="wpaim-ob-pips">
                    <div className="wpaim-ob-pip wpaim-ob-pip--active" />
                    { /* Second pip only shown on own-key path — hidden here */ }
                </div>
                <div className="wpaim-ob-title">Welcome to WP AI Mind</div>
                <div className="wpaim-ob-sub">
                    How would you like to connect? You can change this anytime in Settings.
                </div>
            </div>

            <div className="wpaim-ob-body">
                { choices.map( ( c ) => (
                    <div
                        key={ c.id }
                        className={ `wpaim-ob-choice${ selection === c.id ? ' wpaim-ob-choice--selected' : '' }` }
                        onClick={ () => onSelect( c.id ) }
                    >
                        <div className="wpaim-ob-radio">
                            <div className="wpaim-ob-radio__dot" />
                        </div>
                        <div>
                            <div className="wpaim-ob-choice__title">
                                { c.title }
                                { c.badge && (
                                    <span className="wpaim-pro-badge" style={ { marginLeft: 6 } }>
                                        { c.badge }
                                    </span>
                                ) }
                            </div>
                            <div className="wpaim-ob-choice__desc">{ c.desc }</div>
                        </div>
                    </div>
                ) ) }
            </div>

            <div className="wpaim-ob-footer">
                <button className="wpaim-ob-skip" onClick={ onSkip }>
                    Skip setup
                </button>
                { isPro ? (
                    <a
                        href={ upgradeUrl }
                        className="wpaim-dash-btn wpaim-dash-btn--primary"
                        target="_blank"
                        rel="nofollow noreferrer"
                        onClick={ onSkip }
                    >
                        Go to Upgrade &#x2197;
                    </a>
                ) : (
                    <button className="wpaim-dash-btn wpaim-dash-btn--primary" onClick={ onContinue }>
                        Get started &#x2192;
                    </button>
                ) }
            </div>
        </>
    );
}

// ── Step 2 ────────────────────────────────────────────────────────────────────

function Step2( { onBack, onFinish } ) {
    const [ selectedProvider, setSelectedProvider ] = useState( null );
    const [ apiKey, setApiKey ] = useState( '' );
    const [ imageProvider, setImageProvider ] = useState( null );
    const [ imageOpen, setImageOpen ] = useState( false );
    const [ saving, setSaving ] = useState( false );

    const handleFinish = useCallback( async () => {
        setSaving( true );
        await onFinish( { provider: selectedProvider, apiKey, imageProvider } );
        setSaving( false );
    }, [ onFinish, selectedProvider, apiKey, imageProvider ] );

    return (
        <>
            <div className="wpaim-ob-header">
                <div className="wpaim-ob-pips">
                    <div className="wpaim-ob-pip wpaim-ob-pip--done" />
                    <div className="wpaim-ob-pip wpaim-ob-pip--active" />
                </div>
                <div className="wpaim-ob-title">Choose your AI providers</div>
                <div className="wpaim-ob-sub">
                    Pick a text provider and paste your key. Image generation is optional.
                </div>
            </div>

            <div className="wpaim-ob-body">
                <div className="wpaim-ob-section-label">Text model</div>

                <div className="wpaim-ob-providers">
                    { PROVIDERS.map( ( p ) => (
                        <div
                            key={ p.id }
                            className={ `wpaim-ob-provider${ selectedProvider === p.id ? ' wpaim-ob-provider--selected' : '' }` }
                            onClick={ () => setSelectedProvider( p.id ) }
                        >
                            <div className="wpaim-ob-provider__check">&#x2713;</div>
                            <div className="wpaim-ob-provider__name">{ p.name }</div>
                            <a
                                href={ p.keyUrl }
                                className="wpaim-ob-provider__link"
                                target="_blank"
                                rel="nofollow noreferrer"
                                onClick={ ( e ) => e.stopPropagation() }
                            >
                                { p.keyLabel } &#x2197;
                            </a>
                        </div>
                    ) ) }
                </div>

                { selectedProvider && (
                    <input
                        className="wpaim-ob-key-input"
                        type="password"
                        value={ apiKey }
                        onChange={ ( e ) => setApiKey( e.target.value ) }
                        placeholder={
                            PROVIDERS.find( ( p ) => p.id === selectedProvider )?.placeholder ?? 'Paste your API key'
                        }
                        autoComplete="off"
                    />
                ) }

                { /* Image provider — optional, collapsible */ }
                <div className="wpaim-ob-optional">
                    <button
                        type="button"
                        className="wpaim-ob-optional__toggle"
                        onClick={ () => setImageOpen( ( o ) => ! o ) }
                    >
                        <span className="wpaim-ob-optional__label">Image model</span>
                        <span className="wpaim-ob-optional__tag">Optional</span>
                        <span className={ `wpaim-ob-optional__chevron${ imageOpen ? ' wpaim-ob-optional__chevron--open' : '' }` }>
                            &#x25be;
                        </span>
                    </button>

                    { imageOpen && (
                        <div className="wpaim-ob-optional__body">
                            <p className="wpaim-ob-optional__desc">
                                Add an image generation provider. Can be configured later in Settings.
                            </p>

                            <div className="wpaim-ob-providers">
                                { IMAGE_PROVIDERS.map( ( p ) => (
                                    <div
                                        key={ p.id }
                                        className={ `wpaim-ob-provider${ imageProvider === p.id ? ' wpaim-ob-provider--selected' : '' }` }
                                        onClick={ () => setImageProvider( ( prev ) => prev === p.id ? null : p.id ) }
                                    >
                                        <div className="wpaim-ob-provider__check">&#x2713;</div>
                                        <div className="wpaim-ob-provider__name">{ p.name }</div>
                                        <a
                                            href={ p.docsUrl }
                                            className="wpaim-ob-provider__link"
                                            target="_blank"
                                            rel="nofollow noreferrer"
                                            onClick={ ( e ) => e.stopPropagation() }
                                        >
                                            { p.docsLabel } &#x2197;
                                        </a>
                                    </div>
                                ) ) }
                                <div className="wpaim-ob-provider" style={ { opacity: 0.4, cursor: 'default', borderStyle: 'dashed' } }>
                                    <div className="wpaim-ob-provider__name" style={ { color: 'var(--color-text-muted)', fontSize: '0.625rem' } }>
                                        More coming
                                    </div>
                                </div>
                            </div>


                        </div>
                    ) }
                </div>
            </div>

            <div className="wpaim-ob-footer">
                <button className="wpaim-ob-skip" onClick={ onBack }>
                    &#x2190; Back
                </button>
                <button
                    className="wpaim-dash-btn wpaim-dash-btn--primary"
                    onClick={ handleFinish }
                    disabled={ saving }
                >
                    { saving ? 'Saving…' : 'Finish setup \u2192' }
                </button>
            </div>
        </>
    );
}

// ── Done screen ───────────────────────────────────────────────────────────────

function DoneScreen( { apiTierLabel, urls } ) {
    return (
        <>
            <div className="wpaim-ob-header">
                <div className="wpaim-ob-pips">
                    <div className="wpaim-ob-pip wpaim-ob-pip--done" />
                    <div className="wpaim-ob-pip wpaim-ob-pip--done" />
                </div>
                <div className="wpaim-ob-title">You're all set</div>
                <div className="wpaim-ob-sub">WP AI Mind is ready to use.</div>
            </div>

            <div className="wpaim-ob-body">
                <div className="wpaim-ob-success">
                    <div className="wpaim-ob-success__title">Setup complete</div>
                    <div className="wpaim-ob-success__sub">
                        { apiTierLabel }.<br />
                        Change this anytime in Settings.
                    </div>
                    <a href={ urls.chat } className="wpaim-ob-success__cta">
                        Open Chat &#x2192;
                    </a>
                </div>
            </div>

            <div className="wpaim-ob-footer" style={ { borderTop: 'none', paddingTop: 0, justifyContent: 'center' } }>
                <a href="#" className="wpaim-ob-skip" onClick={ ( e ) => { e.preventDefault(); window.location.reload(); } }>
                    Go to Dashboard instead
                </a>
            </div>
        </>
    );
}

// ── Root modal ────────────────────────────────────────────────────────────────

export default function OnboardingModal( { onDismiss, nonce, restUrl, urls } ) {
    const [ step, setStep ] = useState( 'step1' );        // 'step1' | 'step2' | 'done'
    const [ connection, setConnection ] = useState( 'plugin' ); // 'plugin' | 'own_key' | 'pro'
    const [ apiTierLabel, setApiTierLabel ] = useState( 'Using Plugin API (free tier)' );

    const postOnboarding = useCallback( async ( body ) => {
        await window.fetch( `${ restUrl }/onboarding`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify( body ),
        } );
    }, [ nonce, restUrl ] );

    const handleSkip = useCallback( async () => {
        await postOnboarding( { seen: true } );
        onDismiss();
    }, [ postOnboarding, onDismiss ] );

    const handleStep1Continue = useCallback( async () => {
        if ( connection === 'plugin' ) {
            await postOnboarding( { seen: true } );
            setApiTierLabel( 'Using Plugin API (free tier)' );
            setStep( 'done' );
        } else if ( connection === 'own_key' ) {
            setStep( 'step2' );
        }
        // 'pro' path handled by the upgrade link directly in Step1
    }, [ connection, postOnboarding ] );

    const handleStep2Finish = useCallback( async ( { provider, apiKey, imageProvider } ) => {
        await postOnboarding( { seen: true, provider, api_key: apiKey, image_provider: imageProvider } );
        setApiTierLabel( `Using your own ${ provider ? provider.charAt( 0 ).toUpperCase() + provider.slice( 1 ) : '' } API key` );
        setStep( 'done' );
    }, [ postOnboarding ] );

    return (
        <div className="wpaim-ob-overlay">
            <div className="wpaim-ob-modal">
                { step === 'step1' && (
                    <Step1
                        selection={ connection }
                        onSelect={ setConnection }
                        onContinue={ handleStep1Continue }
                        onSkip={ handleSkip }
                        upgradeUrl={ urls.upgrade }
                    />
                ) }
                { step === 'step2' && (
                    <Step2
                        onBack={ () => setStep( 'step1' ) }
                        onFinish={ handleStep2Finish }
                    />
                ) }
                { step === 'done' && (
                    <DoneScreen
                        apiTierLabel={ apiTierLabel }
                        urls={ urls }
                    />
                ) }
            </div>
        </div>
    );
}
