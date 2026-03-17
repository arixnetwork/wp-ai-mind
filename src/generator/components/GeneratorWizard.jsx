import { useState } from '@wordpress/element';
import { Wand2, Loader2, CheckCircle2, ExternalLink } from 'lucide-react';
import apiFetch from '@wordpress/api-fetch';

const TONES = ['professional', 'casual', 'friendly', 'authoritative', 'witty'];
const LENGTHS = [
	{ value: 'short',  label: 'Short (300–500 words)' },
	{ value: 'medium', label: 'Medium (600–900 words)' },
	{ value: 'long',   label: 'Long (1200–1800 words)' },
];

export default function GeneratorWizard() {
	const [step,   setStep]   = useState(1); // 1=brief, 2=generating, 3=done
	const [form,   setForm]   = useState({ title: '', keywords: '', tone: 'professional', length: 'medium' });
	const [result, setResult] = useState(null); // { post_id, edit_url, content, tokens_used }
	const [error,  setError]  = useState(null);

	function update(field, value) {
		setForm(prev => ({ ...prev, [field]: value }));
	}

	async function generate() {
		if (!form.title.trim()) return;
		setStep(2);
		setError(null);
		try {
			const res = await apiFetch({
				path:   '/wp-ai-mind/v1/generate',
				method: 'POST',
				data:   form,
			});
			setResult(res);
			setStep(3);
		} catch (e) {
			setError(e?.message || 'Generation failed. Please try again.');
			setStep(1);
		}
	}

	if (step === 2) {
		return (
			<div className="wpaim-generator">
				<div className="wpaim-generator__card" style={{ textAlign: 'center', padding: 'var(--space-8)' }}>
					<Loader2 size={32} className="wpaim-spin" style={{ color: 'var(--color-accent)' }} />
					<p style={{ marginTop: 'var(--space-4)', color: 'var(--color-text-muted)' }}>
						Generating your post — this may take a moment…
					</p>
				</div>
			</div>
		);
	}

	if (step === 3 && result) {
		return (
			<div className="wpaim-generator">
				<div className="wpaim-generator__success">
					<CheckCircle2 size={40} style={{ color: 'var(--color-accent)' }} />
					<h2 style={{ marginTop: 'var(--space-3)' }}>Post Generated!</h2>
					<p style={{ color: 'var(--color-text-muted)' }}>{result.tokens_used} tokens used</p>
					<a
						href={result.edit_url}
						className="wpaim-generator__btn wpaim-generator__btn--primary"
						style={{ display: 'inline-flex', alignItems: 'center', gap: 'var(--space-2)', marginTop: 'var(--space-4)', textDecoration: 'none' }}
					>
						<ExternalLink size={14} /> Edit in WordPress
					</a>
				</div>
				<div className="wpaim-generator__card" style={{ marginTop: 'var(--space-4)' }}>
					<h3 style={{ marginBottom: 'var(--space-2)', fontSize: 'var(--text-sm)', color: 'var(--color-text-muted)' }}>Preview</h3>
					<div className="wpaim-generator__preview" dangerouslySetInnerHTML={{ __html: result.content }} />
				</div>
				<div className="wpaim-generator__actions" style={{ marginTop: 'var(--space-4)' }}>
					<button
						className="wpaim-generator__btn wpaim-generator__btn--ghost"
						onClick={() => { setStep(1); setResult(null); }}
					>
						Generate another
					</button>
				</div>
			</div>
		);
	}

	// Step 1 — Brief form
	return (
		<div className="wpaim-generator">
			<div className="wpaim-generator__header">
				<h1 style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
					<Wand2 size={24} style={{ color: 'var(--color-accent)' }} /> Post Generator
				</h1>
				<p style={{ color: 'var(--color-text-muted)' }}>Describe your post and AI will write a full draft.</p>
			</div>

			{error && (
				<div style={{
					background:    'var(--color-error-subtle, rgba(220,38,38,0.1))',
					border:        '1px solid var(--color-error)',
					borderRadius:  'var(--radius-md)',
					padding:       'var(--space-3)',
					marginBottom:  'var(--space-4)',
					color:         'var(--color-error)',
					fontSize:      'var(--text-sm)',
				}}>
					{error}
				</div>
			)}

			<div className="wpaim-generator__card">
				<div className="wpaim-generator__field">
					<label className="wpaim-generator__label">Post title *</label>
					<input
						className="wpaim-generator__input"
						value={form.title}
						onChange={e => update('title', e.target.value)}
						placeholder="e.g. 10 Tips for Better Sleep"
					/>
				</div>

				<div className="wpaim-generator__field">
					<label className="wpaim-generator__label">
						Keywords{' '}
						<span style={{ color: 'var(--color-text-muted)', fontWeight: 400 }}>(optional)</span>
					</label>
					<input
						className="wpaim-generator__input"
						value={form.keywords}
						onChange={e => update('keywords', e.target.value)}
						placeholder="e.g. sleep hygiene, circadian rhythm, melatonin"
					/>
				</div>

				<div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--space-4)' }}>
					<div className="wpaim-generator__field">
						<label className="wpaim-generator__label">Tone</label>
						<select
							className="wpaim-generator__select"
							value={form.tone}
							onChange={e => update('tone', e.target.value)}
						>
							{TONES.map(t => (
								<option key={t} value={t}>{t.charAt(0).toUpperCase() + t.slice(1)}</option>
							))}
						</select>
					</div>
					<div className="wpaim-generator__field">
						<label className="wpaim-generator__label">Length</label>
						<select
							className="wpaim-generator__select"
							value={form.length}
							onChange={e => update('length', e.target.value)}
						>
							{LENGTHS.map(l => (
								<option key={l.value} value={l.value}>{l.label}</option>
							))}
						</select>
					</div>
				</div>

				<div className="wpaim-generator__actions">
					<button
						className="wpaim-generator__btn wpaim-generator__btn--primary"
						onClick={generate}
						disabled={!form.title.trim()}
						style={{ display: 'inline-flex', alignItems: 'center', gap: 'var(--space-2)' }}
					>
						<Wand2 size={14} /> Generate Post
					</button>
				</div>
			</div>
		</div>
	);
}
