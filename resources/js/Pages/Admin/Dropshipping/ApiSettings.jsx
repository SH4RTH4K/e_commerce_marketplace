import DropshippingSubpage from './Subpage';
import { router } from '@inertiajs/react';
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

export default function ApiSettings({ suppliers = [], available_drivers = [], driver_profiles = [] }) {
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
  const [selectedSupplierId, setSelectedSupplierId] = useState('');
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
    const url = selectedSupplier ? `/admin/dropshipping/suppliers/${selectedSupplier.id}` : '/admin/dropshipping/suppliers';
    router[selectedSupplier ? 'patch' : 'post'](url, data, {
      preserveScroll: true,
      onSuccess: page => {
        // After creating a connection, select it automatically so the
        // immediate Test connection button becomes available.
        if (!selectedSupplier) {
          const newest = [...(page.props.suppliers || [])].sort((a, b) => Number(b.id) - Number(a.id))[0];
          if (newest) setSelectedSupplierId(String(newest.id));
        }
      },
    });
  };
  const updateName = event => { const name = event.target.value; setProfileName(name); if (!customProfile) setProfileKey(name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')); };

  return <DropshippingSubpage title="API Settings" description="Create supplier API profiles, connect accounts, and test them immediately.">
    <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
      <div className="flex items-start gap-3 mb-5"><span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-900 text-sm font-bold text-white">1</span><div><h2 className="font-bold text-gray-900">Configure supplier API profile</h2><p className="text-sm text-gray-500 mt-1">Create a profile once, or select a saved profile below to edit its API settings.</p></div></div>
      <div className="mb-4"><Field label="Profile to configure" hint="Select a saved profile to edit it, or choose New profile."><select value={selectedProfileId} onChange={event => selectProfile(event.target.value)} className={`${inputClass} bg-white`}><option value="">New supplier profile</option>{driver_profiles.map(profile => <option key={profile.id} value={profile.id}>{profile.name} ({profile.key})</option>)}</select></Field></div>
      <form onSubmit={saveProfile} className="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
        <Field label="Supplier name" hint="For example: Mohasagor, CJ Dropshipping, or a local supplier."><input name="name" required placeholder="Mohasagor Drop Shipping" value={profileName} onChange={updateName} className={inputClass} /></Field>
        <Field label="Setup type" hint="Use Custom only when the supplier gave you different API instructions."><select value={customProfile ? 'custom' : 'standard'} onChange={event => setCustomProfile(event.target.value === 'custom')} className={`${inputClass} bg-white`}><option value="standard">Standard setup (recommended)</option><option value="custom">I have supplier API instructions</option></select></Field>
        <input type="hidden" name="key" value={profileKey} /><input type="hidden" name="auth_key_header" value={authKey} /><input type="hidden" name="auth_secret_header" value={authSecret} /><input type="hidden" name="products_path" value={productsPath} /><input type="hidden" name="categories_path" value={categoriesPath} /><input type="hidden" name="collection_path" value={collectionPath} /><input type="hidden" name="pagination_param" value={paginationParam} /><input type="hidden" name="default_currency" value={currency} /><input type="hidden" name="field_mapping" value={mapping} />
        {customProfile && <details open className="md:col-span-2 rounded-xl border border-orange-200 bg-orange-50/40 px-4 py-3"><summary className="cursor-pointer text-sm font-semibold text-gray-700">Supplier API instructions</summary><p className="mt-2 text-xs text-gray-600">Copy these values from the supplier’s API documentation. Never paste credentials here.</p><div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3"><Field label="Internal driver key"><input value={profileKey} onChange={event => setProfileKey(event.target.value.toLowerCase())} pattern="[a-z0-9][a-z0-9_-]*" className={inputClass} /></Field><Field label="API-key header name"><input value={authKey} onChange={event => setAuthKey(event.target.value)} className={inputClass} /></Field><Field label="Secret header name"><input value={authSecret} onChange={event => setAuthSecret(event.target.value)} className={inputClass} /></Field><Field label="Products endpoint path"><input value={productsPath} onChange={event => setProductsPath(event.target.value)} className={inputClass} /></Field><Field label="Categories endpoint path"><input value={categoriesPath} onChange={event => setCategoriesPath(event.target.value)} className={inputClass} /></Field><Field label="Product list path"><input value={collectionPath} onChange={event => setCollectionPath(event.target.value)} placeholder="data.items" className={inputClass} /></Field><Field label="Pagination parameter"><input value={paginationParam} onChange={event => setPaginationParam(event.target.value)} className={inputClass} /></Field><Field label="Default currency"><input value={currency} onChange={event => setCurrency(event.target.value.toUpperCase())} maxLength="3" className={`${inputClass} uppercase`} /></Field></div><details className="mt-4 rounded-xl border border-orange-200 bg-white px-4 py-3"><summary className="cursor-pointer text-xs font-semibold text-gray-600">Advanced product field mapping</summary><p className="mt-2 text-xs text-gray-500">Use this only with a sample JSON response.</p><textarea value={mapping} onChange={event => setMapping(event.target.value)} rows="8" spellCheck="false" className="mt-2 w-full border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-mono" /></details></details>}
        <div className="md:col-span-2 flex items-center gap-3"><button className="px-4 py-2.5 rounded-xl bg-gray-900 hover:bg-gray-800 text-sm font-semibold text-white">{selectedProfile ? 'Save profile changes' : 'Save supplier profile'}</button>{selectedProfile && <button type="button" onClick={() => selectProfile('')} className="px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-sm font-semibold text-gray-700">New profile</button>}</div>
      </form>
    </section>

    <section className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
      <div className="flex items-start gap-3 mb-5"><span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-orange-500 text-sm font-bold text-white">2</span><div><h2 className="font-bold text-gray-900">Connect and test a supplier account</h2><p className="text-sm text-gray-500 mt-1">Select an existing account to edit it, or choose New connection to add another account.</p></div></div>
      <div className="mb-4"><Field label="Account to configure"><select value={selectedSupplierId} onChange={event => setSelectedSupplierId(event.target.value)} className={`${inputClass} bg-white`}><option value="">New supplier connection</option>{suppliers.map(supplier => <option key={supplier.id} value={supplier.id}>{supplier.name} ({supplier.key})</option>)}</select></Field></div>
      {!ready && <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Complete Step 1 first. After saving a supplier profile, this form becomes available.</div>}
      <fieldset disabled={!ready}>
        <form key={selectedSupplier?.id || 'new'} onSubmit={saveSupplier} className="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
          <Field label="Supplier account name" hint="A friendly name for this connection."><input name="name" required defaultValue={selectedSupplier?.name || ''} placeholder="Mohasagor Bangladesh" className={inputClass} /></Field>
          <Field label="API Base URL" hint="Use the HTTPS API address supplied by your provider."><input name="base_url" type="url" required defaultValue={selectedSupplier?.base_url || ''} placeholder="https://supplier.example/api" className={inputClass} /></Field>
          <Field label="Supplier driver"><select name="driver_key" required defaultValue={selectedSupplier?.driver_key || ''} className={`${inputClass} bg-white`}><option value="">Select saved supplier profile</option>{available_drivers.map(driver => <option key={driver} value={driver}>{driver}</option>)}</select></Field>
          <Field label="Business key" hint={selectedSupplier ? 'This key identifies the saved account.' : 'Optional. Leave blank to create one automatically.'}><input name="key" defaultValue={selectedSupplier?.key || ''} readOnly={Boolean(selectedSupplier)} placeholder="supplier-main" className={`${inputClass} ${selectedSupplier ? 'bg-gray-50 text-gray-500' : ''}`} /></Field>
          <Field label="API key" hint={selectedSupplier ? 'Leave blank to keep the saved encrypted key.' : 'Stored encrypted and never shown again.'}><input name="api_key" type="password" autoComplete="new-password" placeholder={selectedSupplier ? 'Leave unchanged' : 'Paste API key'} className={inputClass} /></Field>
          <Field label="Secret key" hint={selectedSupplier ? 'Leave blank to keep the saved encrypted secret.' : 'Stored encrypted and never shown again.'}><input name="secret_key" type="password" autoComplete="new-password" placeholder={selectedSupplier ? 'Leave unchanged' : 'Paste secret key'} className={inputClass} /></Field>
          <Field label="Imported product status" hint="Draft is safest for review."><select name="import_status" defaultValue={selectedRules.import_status || 'draft'} className={`${inputClass} bg-white`}><option value="draft">Draft</option><option value="pending">Pending review</option><option value="private">Private</option><option value="publish">Published</option></select></Field>
          <div className="space-y-2 text-sm text-gray-600 pt-6"><label className="flex items-center gap-2"><input type="checkbox" name="import_images" value="1" defaultChecked={Boolean(selectedRules.import_images)} /> Download supplier images</label><label className="flex items-center gap-2"><input type="checkbox" name="import_variations" value="1" defaultChecked={Boolean(selectedRules.import_variations)} /> Import variations</label></div>
          <div className="md:col-span-2 flex flex-wrap items-center gap-3"><button className="px-4 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 text-sm font-semibold text-white">{selectedSupplier ? 'Save changes' : 'Save connection'}</button>{selectedSupplier && <button type="button" onClick={() => router.post(`/admin/dropshipping/suppliers/${selectedSupplier.id}/test`, {}, { preserveScroll: true })} className="px-4 py-2.5 rounded-xl bg-gray-900 hover:bg-gray-800 text-sm font-semibold text-white">Test connection now</button>}<span className="text-xs text-gray-400">Connection tests return immediately. Credentials remain encrypted.</span></div>
        </form>
      </fieldset>
      {selectedSupplier && <div className={`mt-4 rounded-xl border px-4 py-3 text-sm ${selectedSupplier.last_connection_status === 'success' ? 'border-green-200 bg-green-50 text-green-800' : selectedSupplier.last_connection_status === 'failed' ? 'border-red-200 bg-red-50 text-red-800' : 'border-gray-200 bg-gray-50 text-gray-700'}`}><div className="flex items-center gap-2"><strong>Connection status</strong><ConnectionBadge status={selectedSupplier.last_connection_status} /></div><p className="mt-1">{selectedSupplier.last_connection_message || 'This account has not been tested yet.'}</p>{selectedSupplier.last_connection_status === 'failed' && <p className="mt-1">Check the API Base URL, driver header names, API key, and secret key, then save and test again.</p>}</div>}
    </section>

    <section className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden"><div className="px-5 py-4 border-b border-gray-100"><h2 className="font-bold text-gray-900">Saved supplier API profiles</h2><p className="text-xs text-gray-500 mt-1">Edit a profile when the supplier changes its headers, endpoints, or response format.</p></div><table className="w-full text-sm"><tbody className="divide-y divide-gray-50">{driver_profiles.length === 0 ? <tr><td className="px-5 py-8 text-center text-sm text-gray-400">No supplier profiles saved yet.</td></tr> : driver_profiles.map(profile => <tr key={profile.id}><td className="px-5 py-4"><p className="font-semibold text-gray-800">{profile.name}</p><p className="text-xs text-gray-400 font-mono">{profile.key}</p></td><td className="px-5 py-4 text-right"><div className="flex justify-end gap-2"><button onClick={() => selectProfile(profile.id)} className="px-3 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 text-xs font-semibold text-white">Edit</button><button onClick={() => router.patch(`/admin/dropshipping/driver-profiles/${profile.id}/toggle`, {}, { preserveScroll: true })} className="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700">{profile.is_active ? 'Disable' : 'Enable'}</button></div></td></tr>)}</tbody></table></section>

    <section className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden"><div className="px-5 py-4 border-b border-gray-100"><h2 className="font-bold text-gray-900">Connected supplier accounts</h2><p className="text-xs text-gray-500 mt-1">Select an account above to edit all settings, replace credentials, or test it.</p></div><table className="w-full text-sm"><tbody className="divide-y divide-gray-50">{suppliers.length === 0 ? <tr><td className="px-5 py-8 text-center text-sm text-gray-400">No supplier accounts connected.</td></tr> : suppliers.map(supplier => <tr key={supplier.id}><td className="px-5 py-4"><p className="font-semibold text-gray-800">{supplier.name}</p><p className="text-xs text-gray-400 font-mono">{supplier.driver_key} · {supplier.base_url}</p></td><td className="px-5 py-4"><ConnectionBadge status={supplier.last_connection_status} />{supplier.last_connection_status === 'failed' && <p className="mt-1 text-xs text-red-600">{supplier.last_connection_message}</p>}</td><td className="px-5 py-4 text-right"><div className="flex justify-end gap-2"><button onClick={() => setSelectedSupplierId(String(supplier.id))} className="px-3 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 text-xs font-semibold text-white">Edit</button><button onClick={() => router.post(`/admin/dropshipping/suppliers/${supplier.id}/test`, {}, { preserveScroll: true })} className="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-semibold text-gray-700">Test</button></div></td></tr>)}</tbody></table></section>
  </DropshippingSubpage>;
}
