import DropshippingSubpage from './Subpage';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const inputClass = 'mt-1 w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:border-orange-400 focus:ring-orange-200';
const mappingExample = '{\n  "collection_path": "data.items",\n  "fields": {"id":"id", "name":"name", "currency":"currency", "cost_price":"sale_price", "max_price":"price", "category_key":"category_id", "stock_quantity":"stock"},\n  "variant_collection": "product_variants",\n  "variant_fields": {"id":"id", "attributes": {"type":"attribute", "value":"variant"}},\n  "pagination": {"current_page":"meta.current_page", "last_page":"meta.last_page"}\n}';

function Field({ label, hint, children }) {
  return <label className="block text-sm text-gray-700"><span className="font-medium">{label}</span>{hint && <span className="block text-xs text-gray-400 mt-0.5">{hint}</span>}{children}</label>;
}

function ConnectionBadge({ status }) {
  const styles = { success: 'bg-green-50 text-green-700', failed: 'bg-red-50 text-red-700', queued: 'bg-amber-50 text-amber-700', untested: 'bg-gray-100 text-gray-600' };
  return <span className={`inline-flex px-2 py-1 rounded-full text-[11px] font-bold ${styles[status] || styles.untested}`}>{String(status || 'untested').replaceAll('_', ' ')}</span>;
}

export default function ApiSettings({ suppliers = [], selected_supplier_id = null, connect_supplier_id = null, unconnected_suppliers = [], available_drivers = [], driver_profiles = [] }) {
  const [profileName, setProfileName] = useState('');
  const [profileKey, setProfileKey] = useState('');
  const [customProfile, setCustomProfile] = useState(false);
  const [authKey, setAuthKey] = useState('api-key');
  const [authSecret, setAuthSecret] = useState('secret-key');
  const [productsPath, setProductsPath] = useState('product');
  const [categoriesPath, setCategoriesPath] = useState('');
  const [collectionPath, setCollectionPath] = useState('data.items');
  const [paginationParam, setPaginationParam] = useState('page');
  const [currency, setCurrency] = useState('BDT');
  const [mapping, setMapping] = useState(mappingExample);
  const [selectedProfileId, setSelectedProfileId] = useState('');
  const [selectedSupplierId, setSelectedSupplierId] = useState(connect_supplier_id ? '' : String(selected_supplier_id || suppliers[0]?.id || ''));
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);
  const [linkedSupplierId, setLinkedSupplierId] = useState(String(connect_supplier_id || ''));
  const [connectionTarget, setConnectionTarget] = useState(unconnected_suppliers.length ? 'existing' : 'new');
  const { errors = {} } = usePage().props;
  const linkedSupplier = connectionTarget === 'existing' ? unconnected_suppliers.find(item => String(item.id) === linkedSupplierId) : null;
  const selectedProfile = driver_profiles.find(profile => String(profile.id) === String(selectedProfileId));
  const selectedSupplier = suppliers.find(supplier => String(supplier.id) === String(selectedSupplierId));
  const selectedRules = selectedSupplier?.sync_rules || {};
  const ready = available_drivers.length > 0;

  const selectProfile = id => {
    setSelectedProfileId(String(id || ''));
    const profile = driver_profiles.find(item => String(item.id) === String(id));
    if (! profile) {
      setProfileName(''); setProfileKey(''); setCustomProfile(false); setAuthKey('api-key'); setAuthSecret('secret-key'); setProductsPath('product'); setCategoriesPath(''); setCollectionPath('data.items'); setPaginationParam('page'); setCurrency('BDT'); setMapping(mappingExample);
      return;
    }
    setProfileName(profile.name || ''); setProfileKey(profile.key || ''); setCustomProfile(true); setAuthKey(profile.auth_key_header || 'api-key'); setAuthSecret(profile.auth_secret_header || 'secret-key'); setProductsPath(profile.products_path || 'product'); setCategoriesPath(profile.categories_path || ''); setCollectionPath(profile.collection_path || ''); setPaginationParam(profile.pagination_param || 'page'); setCurrency(profile.default_currency || 'BDT'); setMapping(JSON.stringify(profile.field_mapping || {}, null, 2));
  };
  const saveProfile = event => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.currentTarget));
    const url = selectedProfile ? `/admin/dropshipping/driver-profiles/${selectedProfile.id}` : '/admin/dropshipping/driver-profiles';
    router[selectedProfile ? 'patch' : 'post'](url, data, { preserveScroll: true });
  };
  const saveSupplier = event => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.currentTarget));
    for (const flag of ['import_images', 'import_variations']) data[flag] = event.currentTarget.elements[flag].checked ? 1 : 0;
    const url = selectedSupplier ? `/admin/dropshipping/suppliers/${selectedSupplier.id}` : '/admin/dropshipping/suppliers';
    router[selectedSupplier ? 'patch' : 'post'](url, data, {
      preserveScroll: true,
      onStart: () => setSaving(true),
      onFinish: () => setSaving(false),
      onSuccess: page => {
        // After creating a connection, select it automatically so the
        // immediate Test connection button becomes available.
        if (!selectedSupplier) {
          const savedId = page.props.selected_supplier_id || data.supplier_id;
          if (savedId) setSelectedSupplierId(String(savedId));
        }
      },
    });
  };
  const testConnection = supplier => router.post(`/admin/dropshipping/suppliers/${supplier.id}/test`, {}, { preserveScroll: true, onStart: () => setTesting(true), onFinish: () => setTesting(false) });
  const toggleSupplier = supplier => {
    if (supplier.is_active && !window.confirm(`Deactivate ${supplier.name}? New supplier assignments and product sync will be blocked. Existing products and history are retained.`)) return;
    router.patch(`/admin/dropshipping/suppliers/${supplier.id}/toggle`, {}, { preserveScroll: true });
  };
  const updateName = event => { const name = event.target.value; setProfileName(name); if (!customProfile) setProfileKey(name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')); };

  const isManual = false;
  return <DropshippingSubpage supplier={selectedSupplier || linkedSupplier || null} connectionPending={!selectedSupplier} title="Supplier connections" description="Use your saved supplier connection, or add another provider when needed.">
    {Object.keys(errors).length > 0 && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{Object.entries(errors).map(([key, message]) => <p key={key}>{message}</p>)}</div>}
    <div className="flex flex-col gap-5 [&>section]:order-1 [&>details]:order-2">
    <details id="provider-profiles" className="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
      <summary className="cursor-pointer text-sm font-semibold text-gray-600">Advanced: provider profiles & API format</summary>
      <p className="mt-2 text-xs text-gray-500">Mohasagor has a built-in provider. Create a custom profile only when connecting a different API format.</p>
    <section className="bg-white rounded-2xl p-1 pt-5">
      <div className="flex items-start gap-3 mb-5"><span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-900 text-sm font-bold text-white">1</span><div><h2 className="font-bold text-gray-900">Configure supplier API profile</h2><p className="text-sm text-gray-500 mt-1">Create a profile once, or select a saved profile below to edit its API settings.</p></div></div>
      <div className="mb-4"><Field label="Profile to configure" hint="Select a saved profile to edit it, or choose New profile."><select value={selectedProfileId} onChange={event => selectProfile(event.target.value)} className={`${inputClass} bg-white`}><option value="">New supplier profile</option>{driver_profiles.map(profile => <option key={profile.id} value={profile.id}>{profile.name} ({profile.key})</option>)}</select></Field></div>
      <form onSubmit={saveProfile} className="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
        <Field label="Supplier name" hint="For example: Mohasagor, CJ Dropshipping, or a local supplier."><input name="name" required placeholder="Mohasagor Drop Shipping" value={profileName} onChange={updateName} className={inputClass} /></Field>
        <Field label="Setup type" hint="Use Custom only when the supplier gave you different API instructions."><select value={customProfile ? 'custom' : 'standard'} onChange={event => setCustomProfile(event.target.value === 'custom')} className={`${inputClass} bg-white`}><option value="standard">Standard setup (recommended)</option><option value="custom">I have supplier API instructions</option></select></Field>
        <input type="hidden" name="key" value={profileKey} /><input type="hidden" name="auth_key_header" value={authKey} /><input type="hidden" name="auth_secret_header" value={authSecret} /><input type="hidden" name="products_path" value={productsPath} /><input type="hidden" name="categories_path" value={categoriesPath} /><input type="hidden" name="collection_path" value={collectionPath} /><input type="hidden" name="pagination_param" value={paginationParam} /><input type="hidden" name="default_currency" value={currency} /><input type="hidden" name="field_mapping" value={mapping} />
        {customProfile && <details open className="md:col-span-2 rounded-xl border border-orange-200 bg-orange-50/40 px-4 py-3"><summary className="cursor-pointer text-sm font-semibold text-gray-700">Supplier API instructions</summary><p className="mt-2 text-xs text-gray-600">Copy these values from the supplier’s API documentation. Never paste credentials here.</p><div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3"><Field label="Internal driver key"><input value={profileKey} onChange={event => setProfileKey(event.target.value.toLowerCase())} pattern="[a-z0-9][a-z0-9_-]*" className={inputClass} /></Field><Field label="API-key header name"><input value={authKey} onChange={event => setAuthKey(event.target.value)} className={inputClass} /></Field><Field label="Secret header name"><input value={authSecret} onChange={event => setAuthSecret(event.target.value)} className={inputClass} /></Field><Field label="Products endpoint path"><input value={productsPath} onChange={event => setProductsPath(event.target.value)} className={inputClass} /></Field><Field label="Categories endpoint path"><input value={categoriesPath} onChange={event => setCategoriesPath(event.target.value)} className={inputClass} /></Field><Field label="Product list path"><input value={collectionPath} onChange={event => setCollectionPath(event.target.value)} placeholder="data.items" className={inputClass} /></Field><Field label="Pagination parameter"><input value={paginationParam} onChange={event => setPaginationParam(event.target.value)} className={inputClass} /></Field><Field label="Default currency"><input value={currency} onChange={event => setCurrency(event.target.value.toUpperCase())} maxLength="3" className={`${inputClass} uppercase`} /></Field></div><details className="mt-4 rounded-xl border border-orange-200 bg-white px-4 py-3"><summary className="cursor-pointer text-xs font-semibold text-gray-600">Advanced product field mapping</summary><p className="mt-2 text-xs text-gray-500">Use this only with a sample JSON response.</p><textarea value={mapping} onChange={event => setMapping(event.target.value)} rows="8" spellCheck="false" className="mt-2 w-full border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-mono" /></details></details>}
        <div className="md:col-span-2 flex items-center gap-3"><button className="px-4 py-2.5 rounded-xl bg-gray-900 hover:bg-gray-800 text-sm font-semibold text-white">{selectedProfile ? 'Save profile changes' : 'Save supplier profile'}</button>{selectedProfile && <button type="button" onClick={() => selectProfile('')} className="px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-sm font-semibold text-gray-700">New profile</button>}</div>
      </form>
    </section></details>

    <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
      <div className="flex flex-wrap items-start justify-between gap-3 mb-5"><div><h2 className="text-lg font-bold text-gray-900">{selectedSupplier ? selectedSupplier.name : linkedSupplier ? `Connect ${linkedSupplier.name}` : 'Connect a supplier'}</h2><p className="text-sm text-gray-500 mt-1">{selectedSupplier ? 'Your existing setup is saved. Update access only when needed.' : linkedSupplier ? 'Your existing supplier record will be reused. Choose the API provider and enter its access details below.' : 'Choose a supplier, enter API access, then save and test.'}</p></div>{(selectedSupplier || linkedSupplier) && <a href={`/admin/suppliers/${(selectedSupplier || linkedSupplier).id}`} className="text-sm font-semibold text-orange-700">Business details →</a>}</div>
      <div className="mb-4"><Field label="Account to configure"><select value={selectedSupplierId} onChange={event => setSelectedSupplierId(event.target.value)} className={`${inputClass} bg-white`}><option value="">{linkedSupplier ? `Set up ${linkedSupplier.name}` : 'New API connection'}</option>{suppliers.map(supplier => <option key={supplier.id} value={supplier.id}>{supplier.name} ({supplier.supplier_type === 'manual' ? 'Manual' : supplier.key})</option>)}</select></Field></div>
      {!selectedSupplier && <div className="mb-4 grid gap-4 md:grid-cols-2">
        <Field label="Supplier relationship" hint="Reuse the business supplier record so products and history stay together."><select value={connectionTarget} onChange={event => setConnectionTarget(event.target.value)} className={`${inputClass} bg-white`}><option value="existing">Link an existing supplier</option><option value="new">Create a new supplier and connection</option></select></Field>
        {connectionTarget === 'existing' && <Field label="Existing supplier"><select required value={linkedSupplierId} onChange={event => setLinkedSupplierId(event.target.value)} className={`${inputClass} bg-white`}><option value="">Select an active, unconnected supplier</option>{unconnected_suppliers.map(item => <option key={item.id} value={item.id}>{item.name} ({item.supplier_code})</option>)}</select><a href="/admin/suppliers/create" target="_blank" rel="noreferrer" className="mt-2 inline-block text-xs font-semibold text-orange-600">Add supplier in a new tab</a></Field>}
      </div>}
      {!ready && !isManual && <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">No API provider is available. Open Advanced: provider profiles to add your supplier's API format.</div>}
      <fieldset disabled={!ready && !isManual}>
        <form key={selectedSupplier ? `saved-${selectedSupplier.id}` : `new-${connectionTarget}-${linkedSupplierId}`} onSubmit={saveSupplier} className="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
          {!selectedSupplier && connectionTarget === 'existing' && <input type="hidden" name="supplier_id" value={linkedSupplierId} />}
          <input type="hidden" name="supplier_type" value="dropshipping" />
          <Field label="Supplier name" hint={selectedSupplier || connectionTarget === 'existing' ? 'Business details are managed in Suppliers.' : 'A supplier record is created once with this connection.'}><input key={selectedSupplier?.id || linkedSupplierId || 'new-name'} name="name" required defaultValue={selectedSupplier?.name || linkedSupplier?.name || ''} readOnly={Boolean(selectedSupplier) || connectionTarget === 'existing'} placeholder="Supplier company name" className={inputClass} /></Field>
          {!isManual && <><Field label="API Base URL" hint="Use the HTTPS API address supplied by your provider."><input name="base_url" type="url" required defaultValue={selectedSupplier?.base_url || ''} placeholder="https://supplier.example/api" className={inputClass} /></Field>
          {!selectedSupplier && <Field label="API provider" hint="Choose this supplier's provider. Mohasagor has a built-in option."><select name="driver_key" required defaultValue="" className={`${inputClass} bg-white`}><option value="">Select provider</option>{available_drivers.map(driver => <option key={driver} value={driver}>{driver === 'mohasagor' ? 'Mohasagor' : driver}</option>)}</select></Field>}</>}
          {!isManual && <><Field label="API key" hint={selectedSupplier ? 'Leave blank to keep the saved encrypted key.' : 'Stored encrypted and never shown again.'}><input name="api_key" type="password" autoComplete="new-password" placeholder={selectedSupplier ? 'Leave unchanged' : 'Paste API key'} className={inputClass} /></Field>
          <Field label="Secret key" hint={selectedSupplier ? 'Leave blank to keep the saved encrypted secret.' : 'Stored encrypted and never shown again.'}><input name="secret_key" type="password" autoComplete="new-password" placeholder={selectedSupplier ? 'Leave unchanged' : 'Paste secret key'} className={inputClass} /></Field></>}
          <details className="md:col-span-2 rounded-xl border border-gray-200 p-4"><summary className="cursor-pointer text-sm font-semibold text-gray-600">Advanced connection & import settings</summary><div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          {selectedSupplier && <Field label="API provider" hint="Keep the saved provider for existing products."><select name="driver_key" required defaultValue={selectedSupplier.driver_key} className={`${inputClass} bg-white`}>{available_drivers.map(driver => <option key={driver} value={driver}>{driver === 'mohasagor' ? 'Mohasagor' : driver}</option>)}</select></Field>}
          <Field label="Business key" hint={selectedSupplier ? 'Stable account identifier; cannot be changed.' : 'Optional. Generated automatically when blank.'}><input name="key" defaultValue={selectedSupplier?.key || ''} readOnly={Boolean(selectedSupplier)} placeholder="Generated automatically" className={inputClass} /></Field>
          <Field label="Category API path" hint="Optional override from the provider's API instructions."><input name="category_endpoint" defaultValue={selectedRules.category_endpoint || ''} placeholder="Use the provider default" className={inputClass} /></Field>
          <Field label="Imported product status" hint="Draft allows review before publishing."><select name="import_status" defaultValue={selectedRules.import_status || 'draft'} className={`${inputClass} bg-white`}><option value="draft">Draft</option><option value="pending">Pending review</option><option value="private">Private</option><option value="publish">Published</option></select></Field>
          {!isManual && <div className="space-y-2 text-sm text-gray-600 pt-6"><label className="flex items-center gap-2"><input type="checkbox" name="import_images" value="1" defaultChecked={Boolean(selectedRules.import_images)} /> Download supplier images</label><label className="flex items-center gap-2"><input type="checkbox" name="import_variations" value="1" defaultChecked={Boolean(selectedRules.import_variations)} /> Import variations</label></div>}
          </div></details>
          <div className="md:col-span-2 flex flex-wrap items-center gap-3"><button disabled={saving || testing || (!selectedSupplier && connectionTarget === 'existing' && !linkedSupplierId)} className="px-4 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-sm font-semibold text-white">{saving ? 'Saving…' : selectedSupplier ? 'Save changes' : 'Save connection'}</button>{selectedSupplier && <button type="button" disabled={testing || saving} onClick={() => testConnection(selectedSupplier)} className="px-4 py-2.5 rounded-xl bg-gray-900 hover:bg-gray-800 disabled:opacity-50 text-sm font-semibold text-white">{testing ? 'Testing connection…' : 'Test connection'}</button>}<span className="text-xs text-gray-400">Save any access changes before testing.</span></div>
        </form>
      </fieldset>
      {selectedSupplier && <div className={`mt-4 rounded-xl border px-4 py-3 text-sm ${selectedSupplier.last_connection_status === 'success' ? 'border-green-200 bg-green-50 text-green-800' : selectedSupplier.last_connection_status === 'failed' ? 'border-red-200 bg-red-50 text-red-800' : 'border-gray-200 bg-gray-50 text-gray-700'}`}><div className="flex items-center gap-2"><strong>Connection status</strong><ConnectionBadge status={selectedSupplier.last_connection_status} /></div><p className="mt-1">{selectedSupplier.last_connection_message || 'This account has not been tested yet.'}</p>{selectedSupplier.last_connection_status === 'failed' && <p className="mt-1">Check the API Base URL, driver header names, API key, and secret key, then save and test again.</p>}</div>}
      {selectedSupplier && <div className="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4"><a href={`/admin/dropshipping/products?supplier_id=${selectedSupplier.id}`} className="rounded-xl bg-orange-50 px-4 py-2.5 text-sm font-semibold text-orange-800">Browse {selectedSupplier.name} products →</a><a href={`/admin/dropshipping/pricing?supplier_id=${selectedSupplier.id}`} className="text-sm font-semibold text-gray-600">Review pricing</a><button type="button" onClick={() => toggleSupplier(selectedSupplier)} className="ml-auto text-xs font-semibold text-gray-500">{selectedSupplier.is_active ? 'Deactivate supplier' : 'Enable supplier'}</button></div>}
    </section>

    <details className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden"><summary className="cursor-pointer px-5 py-4 text-sm font-semibold text-gray-600">Saved custom API profiles ({driver_profiles.length})</summary><table className="w-full text-sm"><tbody className="divide-y divide-gray-50">{driver_profiles.length === 0 ? <tr><td className="px-5 py-8 text-center text-sm text-gray-400">Mohasagor uses its built-in provider. No custom profile is required.</td></tr> : driver_profiles.map(profile => <tr key={profile.id}><td className="px-5 py-4"><p className="font-semibold text-gray-800">{profile.name}</p><p className="text-xs text-gray-400 font-mono">{profile.key}</p></td><td className="px-5 py-4 text-right"><div className="flex justify-end gap-2"><button onClick={() => { selectProfile(profile.id); document.querySelector('details')?.setAttribute('open', ''); window.scrollTo({ top: 0, behavior: 'smooth' }); }} className="px-3 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 text-xs font-semibold text-white">Edit</button><button onClick={() => router.patch(`/admin/dropshipping/driver-profiles/${profile.id}/toggle`, {}, { preserveScroll: true })} className="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700">{profile.is_active ? 'Disable' : 'Enable'}</button></div></td></tr>)}</tbody></table></details>

    </div>
  </DropshippingSubpage>;
}
