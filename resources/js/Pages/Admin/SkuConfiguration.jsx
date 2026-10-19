import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

const components = [
    { key: 'static_prefix', label: 'Static prefix', example: 'TL', description: 'Your optional store prefix.' },
    { key: 'category', label: 'Category code', example: 'MEN', description: 'The top-level category prefix.' },
    { key: 'subcategory', label: 'Subcategory code', example: 'SHIRT', description: 'The second-level category prefix.' },
    { key: 'subsubcategory', label: 'Sub-subcategory code', example: 'CASUAL', description: 'The third-level category prefix.' },
    { key: 'sequence', label: 'Sequence number', example: '000001', description: 'A unique, increasing store sequence.' },
    { key: 'local_id', label: 'Local product ID', example: '4582', description: 'The product ID in your store.' },
    { key: 'supplier_code', label: 'Supplier code', example: 'MGB', description: 'The code from supplier management.' },
    { key: 'supplier_product_id', label: 'Supplier product ID', example: '9900', description: 'The supplier reference used for sync.' },
    { key: 'supplier_sku', label: 'Supplier SKU', example: '12900', description: 'The supplier product code.' },
    { key: 'date', label: 'Creation month', example: '202610', description: 'The product creation date as YYYYMM.' },
    { key: 'suffix', label: 'Static suffix', example: 'BD', description: 'Your optional ending code.' },
];

const defaults = { enabled: false, components: ['category', 'sequence'], static_prefix: '', suffix: '', separator: '-', case: 'upper', sequence_start: 1, sequence_padding: 6, category_codes: {} };
const control = 'mt-1.5 block w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-orange-400 focus:ring-2 focus:ring-orange-100 disabled:bg-gray-50';
const button = 'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50';
const levelLabels = ['Category', 'Subcategory', 'Sub-subcategory'];

function normalizedConfig(config) {
    return { ...defaults, ...config, components: Array.isArray(config?.components) ? [...config.components] : [...defaults.components], category_codes: { ...(config?.category_codes || {}) } };
}

function humanize(value) {
    return String(value || '').replaceAll('_', ' ').replace(/^./, first => first.toUpperCase());
}

function dateLabel(value) {
    const date = new Date(value);
    return value && !Number.isNaN(date.getTime()) ? date.toLocaleString() : '—';
}

function Field({ label, children, help }) {
    return <label className="block text-sm font-semibold text-gray-700">{label}{children}{help && <span className="mt-1.5 block text-xs font-normal leading-5 text-gray-500">{help}</span>}</label>;
}

export default function SkuConfiguration({ config = {}, categories = [], history = [], totals = {}, next_sequence = 1 }) {
    const { errors = {} } = usePage().props;
    const [data, setData] = useState(() => normalizedConfig(config));
    const [tab, setTab] = useState('format');
    const [categorySearch, setCategorySearch] = useState('');
    const [categoryLevel, setCategoryLevel] = useState('all');
    const [scope, setScope] = useState('missing');
    const [source, setSource] = useState('all');
    const [preview, setPreview] = useState(null);
    const [previewError, setPreviewError] = useState('');
    const [previewBusy, setPreviewBusy] = useState(false);
    const [saving, setSaving] = useState(false);
    const [applying, setApplying] = useState(false);
    const [confirmed, setConfirmed] = useState(false);
    const previewVersion = useRef(0);

    useEffect(() => {
        setData(normalizedConfig(config));
        previewVersion.current += 1;
        setPreview(null);
        setConfirmed(false);
    }, [config]);

    const invalidatePreview = () => {
        previewVersion.current += 1;
        setPreview(null);
        setPreviewError('');
        setConfirmed(false);
    };

    const change = (key, value) => {
        invalidatePreview();
        setData(current => ({ ...current, [key]: value }));
    };

    const toggleComponent = key => change('components', data.components.includes(key) ? data.components.filter(item => item !== key) : [...data.components, key]);
    const moveComponent = (index, direction) => {
        const reordered = [...data.components];
        [reordered[index], reordered[index + direction]] = [reordered[index + direction], reordered[index]];
        change('components', reordered);
    };

    const example = data.components.map(key => {
        if (key === 'static_prefix' || key === 'suffix') return data[key];
        if (key === 'sequence') return String(Math.max(Number(next_sequence) || 1, Number(data.sequence_start) || 1)).padStart(Math.min(12, Math.max(1, Number(data.sequence_padding) || 1)), '0');
        if (key === 'date') {
            const now = new Date();
            return `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}`;
        }
        return components.find(item => item.key === key)?.example || '';
    }).filter(Boolean).join(data.separator);
    const formattedExample = data.case === 'lower' ? example.toLowerCase() : example.toUpperCase();
    const hasSelectedComponent = data.components.length > 0;

    const categoryRows = useMemo(() => {
        const categoryMap = new Map(categories.map(category => [String(category.id), category]));
        return categories.map(category => {
            let depth = 0;
            let parent = categoryMap.get(String(category.parent_id));
            const visited = new Set([String(category.id)]);
            const names = [category.name];
            while (parent && !visited.has(String(parent.id))) {
                visited.add(String(parent.id));
                names.unshift(parent.name);
                depth += 1;
                parent = categoryMap.get(String(parent.parent_id));
            }
            return { ...category, depth, pathLabel: Array.isArray(category.path) ? category.path.join(' / ') : category.path || names.join(' / ') };
        });
    }, [categories]);

    const visibleCategories = categoryRows.filter(category => (categoryLevel === 'all' || category.depth === Number(categoryLevel)) && `${category.pathLabel} ${data.category_codes[category.id] || ''}`.toLowerCase().includes(categorySearch.toLowerCase().trim()));
    const missingCodes = categoryRows.filter(category => category.depth < 3 && data.components.includes(['category', 'subcategory', 'subsubcategory'][category.depth]) && !String(data.category_codes[category.id] || '').trim()).length;

    const save = event => {
        event.preventDefault();
        invalidatePreview();
        setSaving(true);
        router.patch('/admin/sku-configuration', data, { preserveScroll: true, onFinish: () => setSaving(false) });
    };

    const requestPreview = async () => {
        invalidatePreview();
        const version = previewVersion.current;
        setPreviewBusy(true);
        try {
            const response = await fetch('/admin/sku-configuration/preview', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ config: data, scope, source }),
            });
            const body = await response.json().catch(() => null);
            if (response.redirected || (response.ok && !body?.token)) throw new Error('Your session expired. Refresh the page and try again.');
            if (!response.ok) {
                const details = body?.errors ? Object.values(body.errors).flat().join(' ') : '';
                throw new Error(details || body?.message || (response.status === 419 ? 'Your session expired. Refresh the page and try again.' : 'The SKU preview could not be prepared. Please try again.'));
            }
            if (previewVersion.current === version) setPreview(body);
        } catch (error) {
            if (previewVersion.current === version) setPreviewError(error.message || 'Unable to connect. Please try again.');
        } finally {
            setPreviewBusy(false);
        }
    };

    const apply = () => {
        if (!preview?.token || !confirmed || preview.errors?.length || !preview.changed_count || applying) return;
        setApplying(true);
        router.post('/admin/sku-configuration/apply', { token: preview.token, confirm: true }, {
            preserveScroll: true,
            onSuccess: invalidatePreview,
            onError: () => { setConfirmed(false); },
            onFinish: () => setApplying(false),
        });
    };

    return <AdminLayout title="SKU Configuration">
        <Head title="SKU Configuration" />
        <div className="mx-auto max-w-7xl space-y-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div><p className="text-xs font-bold uppercase tracking-widest text-orange-600">Products & Stock</p><h1 className="mt-1 text-2xl font-bold tracking-tight text-gray-900">SKU Configuration</h1><p className="mt-2 max-w-3xl text-sm leading-6 text-gray-500">Build your store SKU from independent components, set category prefixes, and preview changes to existing products.</p></div>
                <span className={`rounded-full px-3 py-1.5 text-xs font-semibold ${config.enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>Automatic generation {config.enabled ? 'enabled' : 'disabled'}</span>
            </div>

            {Object.keys(errors).length > 0 && <div role="alert" className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p className="font-semibold">Please check the following:</p><ul className="mt-2 list-inside list-disc space-y-1">{Object.entries(errors).map(([key, message]) => <li key={key}>{message}</li>)}</ul></div>}

            <div className="overflow-x-auto border-b border-gray-200">
                <nav className="flex min-w-max gap-1" aria-label="SKU configuration sections">{[
                    ['format', 'SKU format & automation'], ['prefixes', 'Prefix setup'], ['existing', 'Update existing products'], ['history', 'Change history'],
                ].map(([key, label]) => <button key={key} type="button" onClick={() => setTab(key)} aria-current={tab === key ? 'page' : undefined} className={`border-b-2 px-4 py-3 text-sm font-semibold transition ${tab === key ? 'border-orange-500 text-orange-700' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-900'}`}>{label}</button>)}</nav>
            </div>

            <form onSubmit={save} className="space-y-6">
                {(tab === 'format' || tab === 'prefixes') && <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div><p className="text-xs font-bold uppercase tracking-widest text-gray-400">Example SKU</p><p className="mt-2 break-all font-mono text-xl font-bold text-gray-900 sm:text-2xl">{formattedExample || 'Choose at least one component'}</p><p className="mt-2 text-xs text-gray-500">Illustrative values. Existing-product preview uses actual product data.</p></div>
                        <button disabled={saving || applying || !hasSelectedComponent} className={`${button} bg-orange-500 text-white hover:bg-orange-600`}>{saving ? 'Saving…' : 'Save configuration'}</button>
                    </div>
                </div>}

                {tab === 'format' && <>
                    <section className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 className="text-lg font-bold text-gray-900">Automatic SKUs for new products</h2>
                        <label className="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4"><input type="checkbox" checked={Boolean(data.enabled)} onChange={event => change('enabled', event.target.checked)} className="mt-1 h-4 w-4 accent-orange-500" /><span><span className="block text-sm font-semibold text-gray-900">Generate local SKUs automatically</span><span className="mt-1 block text-sm leading-6 text-gray-500">Applies to new manual products and supplier imports. A SKU entered manually is kept. Saving this configuration does not change existing products.</span></span></label>
                    </section>

                    <div className="grid gap-6 xl:grid-cols-2">
                        <section className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                            <h2 className="text-lg font-bold text-gray-900">Choose independent components</h2><p className="mt-1 text-sm text-gray-500">Use any combination, such as category code and sequence only.</p>
                            <div className="mt-5 space-y-2">{components.map(component => <label key={component.key} className={`flex cursor-pointer gap-3 rounded-xl border p-3 ${data.components.includes(component.key) ? 'border-orange-200 bg-orange-50/60' : 'border-gray-100 hover:bg-gray-50'}`}><input type="checkbox" checked={data.components.includes(component.key)} onChange={() => toggleComponent(component.key)} className="mt-1 h-4 w-4 shrink-0 accent-orange-500" /><span><span className="text-sm font-semibold text-gray-800">{component.label}</span><span className="mt-0.5 block text-xs leading-5 text-gray-500">{component.description}</span></span></label>)}</div>
                        </section>

                        <section className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                            <h2 className="text-lg font-bold text-gray-900">Order and format</h2><p className="mt-1 text-sm text-gray-500">Move components to choose how the SKU reads.</p>
                            <ol className="mt-5 space-y-2">{data.components.map((key, index) => <li key={key} className="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-2"><span className="flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">{index + 1}</span><span className="flex-1 text-sm font-medium text-gray-800">{components.find(item => item.key === key)?.label || humanize(key)}</span><button type="button" aria-label={`Move ${humanize(key)} up`} disabled={index === 0} onClick={() => moveComponent(index, -1)} className="rounded-lg px-2 py-1 text-gray-500 hover:bg-gray-100 disabled:opacity-25">↑</button><button type="button" aria-label={`Move ${humanize(key)} down`} disabled={index === data.components.length - 1} onClick={() => moveComponent(index, 1)} className="rounded-lg px-2 py-1 text-gray-500 hover:bg-gray-100 disabled:opacity-25">↓</button></li>)}</ol>
                            {!hasSelectedComponent && <p className="mt-3 text-sm text-red-600">Choose at least one component before saving.</p>}
                            <div className="mt-6 grid gap-4 sm:grid-cols-2">
                                <Field label="Static prefix"><input className={control} maxLength={30} value={data.static_prefix} onChange={event => change('static_prefix', event.target.value)} placeholder="TL" /></Field>
                                <Field label="Static suffix"><input className={control} maxLength={30} value={data.suffix} onChange={event => change('suffix', event.target.value)} placeholder="BD" /></Field>
                                <Field label="Separator"><select className={control} value={data.separator} onChange={event => change('separator', event.target.value)}><option value="-">Hyphen (-)</option><option value="_">Underscore (_)</option><option value="">No separator</option></select></Field>
                                <Field label="Letter case"><select className={control} value={data.case} onChange={event => change('case', event.target.value)}><option value="upper">Uppercase</option><option value="lower">Lowercase</option></select></Field>
                                <Field label="Sequence starting number" help="The sequence never moves backward below the next available number."><input className={control} type="number" min="1" step="1" value={data.sequence_start} onChange={event => change('sequence_start', event.target.value)} /></Field>
                                <Field label="Sequence digits" help="6 digits produces 000001. Longer numbers are kept in full."><input className={control} type="number" min="1" max="12" step="1" value={data.sequence_padding} onChange={event => change('sequence_padding', event.target.value)} /></Field>
                            </div>
                            <p className="mt-4 rounded-xl bg-gray-50 p-3 text-xs leading-5 text-gray-600">Next available sequence: <strong className="font-mono text-gray-900">{Number(next_sequence).toLocaleString()}</strong>. Sequence and local product ID help keep generated SKUs unique.</p>
                        </section>
                    </div>
                    <div className="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm leading-6 text-blue-900">Category prefixes are managed separately in <button type="button" onClick={() => setTab('prefixes')} className="font-semibold underline underline-offset-2">Prefix setup</button>. Supplier IDs and supplier SKUs keep their original values for synchronization. Variant offers use the local parent SKU with an option suffix; supplier variant references remain separate.</div>
                </>}

                {tab === 'prefixes' && <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div className="p-5 sm:p-6"><div className="flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-lg font-bold text-gray-900">Category prefix setup</h2><p className="mt-1 max-w-3xl text-sm leading-6 text-gray-500">Set an independent code for each category, subcategory, and sub-subcategory. For example: MEN / SHIRT / CASUAL.</p></div><Link href="/admin/categories" className="text-sm font-semibold text-orange-600 hover:text-orange-700">Manage categories →</Link></div>
                        <div className="mt-5 grid gap-4 sm:grid-cols-[1fr_220px]"><Field label="Search categories or codes"><input type="search" className={control} value={categorySearch} onChange={event => setCategorySearch(event.target.value)} placeholder="Search category, path or prefix…" /></Field><Field label="Category level"><select className={control} value={categoryLevel} onChange={event => setCategoryLevel(event.target.value)}><option value="all">All levels</option><option value="0">Category</option><option value="1">Subcategory</option><option value="2">Sub-subcategory</option></select></Field></div>
                        <p className={`mt-4 rounded-xl p-3 text-sm leading-6 ${missingCodes ? 'bg-amber-50 text-amber-900' : 'bg-gray-50 text-gray-600'}`}>{missingCodes ? `${missingCodes} categories have no code for an enabled SKU component. Products using those categories cannot receive a generated SKU until the code is set.` : 'Codes are required only for hierarchy levels enabled in your SKU format. A missing required level or code blocks generation for that product.'}</p>
                    </div>
                    <div className="max-h-[560px] overflow-auto"><table className="w-full text-left text-sm"><thead className="sticky top-0 bg-gray-50 text-xs uppercase tracking-wide text-gray-400"><tr><th className="px-5 py-3 font-semibold sm:px-6">Category hierarchy</th><th className="hidden px-4 py-3 font-semibold sm:table-cell">Level</th><th className="w-40 px-5 py-3 font-semibold sm:w-56 sm:px-6">Prefix / code</th></tr></thead><tbody className="divide-y divide-gray-100">{visibleCategories.map(category => <tr key={category.id}><td className="px-5 py-4 sm:px-6"><p className="font-semibold text-gray-800">{category.name}</p><p className="mt-1 text-xs leading-5 text-gray-500">{category.pathLabel}</p><span className="mt-1 block text-xs text-gray-400 sm:hidden">{levelLabels[category.depth] || 'Nested category'}</span></td><td className="hidden px-4 py-4 text-gray-500 sm:table-cell">{levelLabels[category.depth] || 'Nested category'}</td><td className="px-5 py-4 sm:px-6"><input aria-label={`Prefix for ${category.pathLabel}`} className="w-full rounded-lg border border-gray-200 px-3 py-2 font-mono text-sm outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100" maxLength={30} value={data.category_codes[category.id] || ''} placeholder={['MEN', 'SHIRT', 'CASUAL'][category.depth] || 'CODE'} onChange={event => change('category_codes', { ...data.category_codes, [category.id]: event.target.value })} /></td></tr>)}{visibleCategories.length === 0 && <tr><td colSpan={3} className="px-6 py-12 text-center text-gray-500">{categories.length ? 'No categories match your search.' : 'Create categories before assigning prefixes.'}</td></tr>}</tbody></table></div>
                    <div className="border-t border-gray-100 px-5 py-3 text-xs text-gray-500 sm:px-6">Showing {visibleCategories.length} of {categories.length} categories. Save configuration to keep your prefix changes.</div>
                </section>}

                {tab === 'existing' && <>
                    <section className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6"><h2 className="text-lg font-bold text-gray-900">Preview before changing existing SKUs</h2><p className="mt-2 max-w-3xl text-sm leading-6 text-gray-500">Use your current configuration and prefixes to prepare a review. Applying the preview saves that configuration and updates the selected products together.</p>
                        <div className="mt-5 grid gap-4 sm:grid-cols-2"><Field label="Products to update"><select disabled={applying} className={control} value={scope} onChange={event => { invalidatePreview(); setScope(event.target.value); }}><option value="missing">Only products missing a SKU ({Number(totals.missing || 0).toLocaleString()})</option><option value="all">All existing products ({Number(totals.products || 0).toLocaleString()})</option></select></Field><Field label="Product source"><select disabled={applying} className={control} value={source} onChange={event => { invalidatePreview(); setSource(event.target.value); }}><option value="all">All sources</option><option value="manual">Manual products</option><option value="dropshipping">Dropshipping imports</option></select></Field></div>
                        <div className="mt-5 rounded-xl bg-gray-50 p-4"><span className="text-xs font-bold uppercase tracking-wide text-gray-400">Current format example</span><p className="mt-2 break-all font-mono text-lg font-semibold text-gray-900">{formattedExample || 'No components selected'}</p><button type="button" onClick={() => setTab('format')} className="mt-2 text-xs font-semibold text-orange-600">Edit format</button></div>
                        <button type="button" disabled={previewBusy || applying || !hasSelectedComponent} onClick={requestPreview} className={`${button} mt-5 bg-gray-900 text-white hover:bg-gray-700`}>{previewBusy ? 'Preparing preview…' : 'Preview SKU changes'}</button>
                        {previewError && <p role="alert" className="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{previewError}</p>}
                    </section>

                    {preview && <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" aria-live="polite"><div className="p-5 sm:p-6"><h2 className="text-lg font-bold text-gray-900">Review SKU changes</h2><div className="mt-4 grid grid-cols-3 gap-3">{[['Matched products', preview.total], ['SKUs to change', preview.changed_count], ['Issues to resolve', preview.errors?.length || 0]].map(([label, value]) => <div key={label} className="rounded-xl bg-gray-50 p-3"><p className="text-xs text-gray-500">{label}</p><p className="mt-1 text-xl font-bold text-gray-900">{Number(value || 0).toLocaleString()}</p></div>)}</div>
                        {preview.errors?.length > 0 && <div role="alert" className="mt-4 rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-800"><p className="font-semibold">Resolve these issues before applying.</p><ul className="mt-2 max-h-64 list-inside list-disc space-y-2 overflow-y-auto">{preview.errors.map((error, index) => <li key={`${error.id}-${index}`}><span className="font-semibold">{error.name || `Product #${error.id}`}:</span> {error.message}</li>)}</ul></div>}
                        {!preview.changed_count && !preview.errors?.length && <p className="mt-4 text-sm text-gray-500">No SKU changes are needed for these products.</p>}
                    </div>
                        {preview.samples?.length > 0 && <div className="overflow-x-auto"><table className="w-full min-w-[580px] text-left text-sm"><thead className="bg-gray-50 text-xs uppercase tracking-wide text-gray-400"><tr><th className="px-6 py-3 font-semibold">Product</th><th className="px-6 py-3 font-semibold">Current SKU</th><th className="px-6 py-3 font-semibold">Proposed SKU</th></tr></thead><tbody className="divide-y divide-gray-100">{preview.samples.map(sample => <tr key={sample.id}><td className="max-w-xs px-6 py-4 font-medium text-gray-800">{sample.name}<span className="mt-1 block text-xs font-normal text-gray-400">Local ID: {sample.id}</span></td><td className="break-all px-6 py-4 font-mono text-gray-500">{sample.old_sku || 'Missing'}</td><td className="break-all px-6 py-4 font-mono font-semibold text-gray-900">{sample.new_sku}</td></tr>)}</tbody></table></div>}
                        <div className="border-t border-gray-100 p-5 sm:p-6"><p className="text-xs leading-5 text-gray-500">This is a sample of the affected products. The server checks all matched products for missing data and duplicate SKUs. Editing the format, prefixes, or filters requires a new preview.</p><label className="mt-4 flex items-start gap-3 text-sm leading-6 text-gray-700"><input type="checkbox" checked={confirmed} disabled={applying || Boolean(preview.errors?.length) || !preview.changed_count} onChange={event => setConfirmed(event.target.checked)} className="mt-1.5 h-4 w-4 shrink-0 accent-orange-500" /><span>I reviewed the preview and want to update {Number(preview.changed_count || 0).toLocaleString()} local SKUs and save this configuration.</span></label><button type="button" onClick={apply} disabled={!confirmed || !preview.token || Boolean(preview.errors?.length) || !preview.changed_count || applying} className={`${button} mt-4 bg-orange-500 text-white hover:bg-orange-600`}>{applying ? 'Applying SKU changes…' : 'Save configuration & update SKUs'}</button></div>
                    </section>}
                </>}

                {tab === 'history' && <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"><div className="p-5 sm:p-6"><h2 className="text-lg font-bold text-gray-900">Configuration and update history</h2><p className="mt-1 text-sm text-gray-500">Review who saved the configuration or changed existing local SKUs.</p></div><div className="overflow-x-auto"><table className="w-full min-w-[620px] text-left text-sm"><thead className="bg-gray-50 text-xs uppercase tracking-wide text-gray-400"><tr><th className="px-6 py-3 font-semibold">Action</th><th className="px-6 py-3 font-semibold">Admin</th><th className="px-6 py-3 font-semibold">Scope</th><th className="px-6 py-3 font-semibold">SKUs changed</th><th className="px-6 py-3 font-semibold">Date</th></tr></thead><tbody className="divide-y divide-gray-100">{history.map((entry, index) => <tr key={entry.id || index}><td className="px-6 py-4 font-medium text-gray-800">{humanize(entry.action)}</td><td className="px-6 py-4 text-gray-600">{entry.actor_name || 'System'}</td><td className="px-6 py-4 text-gray-600">{humanize(entry.scope) || 'Configuration'}</td><td className="px-6 py-4 text-gray-600">{Number(entry.changed_count || 0).toLocaleString()}</td><td className="px-6 py-4 whitespace-nowrap text-gray-500">{dateLabel(entry.created_at)}</td></tr>)}{history.length === 0 && <tr><td colSpan={5} className="px-6 py-12 text-center text-gray-500">No SKU configuration changes have been recorded yet.</td></tr>}</tbody></table></div></section>}
            </form>
        </div>
    </AdminLayout>;
}
