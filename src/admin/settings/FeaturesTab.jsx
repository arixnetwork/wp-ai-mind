import { Lock } from 'lucide-react';

const MODULES = [
    {
        slug:        'chat',
        label:       'Chat',
        description: 'AI chat assistant panel in the WordPress admin.',
        isPro:       false,
    },
    {
        slug:        'text_rewrite',
        label:       'Text Rewrite',
        description: 'Rewrite and improve post content using AI.',
        isPro:       false,
    },
    {
        slug:        'summaries',
        label:       'Summaries',
        description: 'Automatically generate post summaries and excerpts.',
        isPro:       false,
    },
    {
        slug:        'seo',
        label:       'SEO',
        description: 'AI-assisted meta titles, descriptions, and keyword suggestions.',
        isPro:       true,
    },
    {
        slug:        'images',
        label:       'Images',
        description: 'Generate and insert AI images directly into posts.',
        isPro:       true,
    },
    {
        slug:        'generator',
        label:       'Generator',
        description: 'Full AI-driven post and page generation workflow.',
        isPro:       true,
    },
    {
        slug:        'frontend_widget',
        label:       'Frontend Widget',
        description: 'Embed an AI chat widget on the public-facing site.',
        isPro:       true,
    },
    {
        slug:        'usage',
        label:       'Usage',
        description: 'Track token consumption and API usage across providers.',
        isPro:       false,
    },
];

export default function FeaturesTab( { settings, saveSettings } ) {
    const enabledModules = settings?.enabled_modules ?? [];
    const isPro          = settings?.is_pro          ?? false;

    function handleToggle( slug, isProModule ) {
        if ( isProModule && ! isPro ) return; // locked

        const isEnabled  = enabledModules.includes( slug );
        const updatedArray = isEnabled
            ? enabledModules.filter( s => s !== slug )
            : [ ...enabledModules, slug ];

        saveSettings( { enabled_modules: updatedArray } );
    }

    return (
        <div className="wpaim-features-tab">
            <section className="wpaim-settings-section">
                <div className="wpaim-settings-section-header">
                    <h3 className="wpaim-settings-section-title">Enabled Modules</h3>
                    <p className="wpaim-settings-section-desc">
                        Enable or disable individual AI modules. Pro modules require an active
                        WP AI Mind Pro licence.
                    </p>
                </div>

                <div className="wpaim-features-grid">
                    { MODULES.map( ( { slug, label, description, isPro: isProModule } ) => {
                        const isEnabled = enabledModules.includes( slug );
                        const isLocked  = isProModule && ! isPro;

                        return (
                            <div
                                key={ slug }
                                className={ `wpaim-feature-card${ isLocked ? ' wpaim-feature-card--locked' : '' }${ isEnabled ? ' wpaim-feature-card--enabled' : '' }` }
                            >
                                <div className="wpaim-feature-card-header">
                                    <div className="wpaim-feature-card-meta">
                                        <span className="wpaim-feature-card-label">{ label }</span>
                                        { isProModule && (
                                            <span className="wpaim-pro-badge">
                                                { isLocked && <Lock size={ 10 } /> }
                                                Pro
                                            </span>
                                        ) }
                                    </div>

                                    <label
                                        className={ `wpaim-toggle${ isLocked ? ' wpaim-toggle--disabled' : '' }` }
                                        aria-label={ `${ isEnabled ? 'Disable' : 'Enable' } ${ label }` }
                                    >
                                        <input
                                            type="checkbox"
                                            className="wpaim-toggle__input"
                                            checked={ isEnabled }
                                            disabled={ isLocked }
                                            onChange={ () => handleToggle( slug, isProModule ) }
                                        />
                                        <span className="wpaim-toggle__track" aria-hidden="true" />
                                    </label>
                                </div>

                                <p className="wpaim-feature-card-desc">{ description }</p>

                                { isLocked && (
                                    <p className="wpaim-feature-card-locked-msg">
                                        <Lock size={ 11 } />
                                        Requires WP AI Mind Pro
                                    </p>
                                ) }
                            </div>
                        );
                    } ) }
                </div>
            </section>
        </div>
    );
}
