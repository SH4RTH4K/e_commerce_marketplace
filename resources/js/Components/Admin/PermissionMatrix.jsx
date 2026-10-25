import { useState } from 'react';

const actions = { view: 'View', add: 'Add', edit: 'Edit', delete: 'Delete', execute: 'Execute' };

export default function PermissionMatrix({ groups = {}, value = [], onChange, disabled = false }) {
  const [search, setSearch] = useState('');
  const selected = new Set(value);
  const entries = Object.entries(groups).filter(([, group]) => group.label.toLowerCase().includes(search.toLowerCase()));
  const keys = entries.filter(([, group]) => !group.super_admin_only).flatMap(([, group]) => Object.keys(group.items || {}));
  const toggle = (permissions) => {
    const next = new Set(value);
    const remove = permissions.every((key) => next.has(key));
    permissions.forEach((key) => remove ? next.delete(key) : next.add(key));
    onChange(Array.from(next));
  };

  return (
    <div className="space-y-3">
      <p className="text-xs text-gray-500">Select View to open a function in the menu, then choose its actions separately. Execute covers jobs, imports, sync, exports, approvals, restores and deployment where available. A dash means the function has no such action.</p>
      <div className="flex flex-wrap items-center gap-3">
        <input type="search" aria-label="Search permission functions" placeholder="Find a function..." value={search} onChange={event => setSearch(event.target.value)} className="min-w-48 flex-1 rounded-xl border border-gray-200 px-3 py-2 text-sm" />
        <button type="button" disabled={disabled} onClick={() => toggle(keys)} className="text-xs font-semibold text-orange-600 disabled:opacity-50">{keys.length > 0 && keys.every(key => selected.has(key)) ? 'Clear shown' : 'Select shown'}</button>
      </div>
      <div className="overflow-x-auto rounded-xl border border-gray-200">
        <table className="w-full min-w-[680px] text-sm">
          <thead className="bg-gray-50 text-xs text-gray-600"><tr><th className="px-3 py-3 text-left">Function</th>{Object.values(actions).map(label => <th key={label} className="px-3 py-3 text-center">{label}</th>)}</tr></thead>
          <tbody className="divide-y divide-gray-100">
            {entries.map(([module, group]) => (
              <tr key={module}>
                <th scope="row" className="px-3 py-3 text-left font-medium text-gray-700">{group.label}{group.hint && <span className="mt-1 block text-xs font-normal text-gray-400">{group.hint}</span>}</th>
                {Object.entries(actions).map(([action, label]) => {
                  const key = `${module}.${action}`;
                  return <td key={action} className="px-3 py-3 text-center">{!Object.hasOwn(group.items || {}, key) ? <span className="text-gray-300">—</span> : group.super_admin_only ? <span title="Super Admin only" className="text-gray-400">Locked</span> : <input type="checkbox" aria-label={`${group.label}: ${label}`} checked={selected.has(key)} disabled={disabled} onChange={() => toggle([key])} className="rounded border-gray-300 text-orange-500 focus:ring-orange-400 disabled:opacity-50" />}</td>;
                })}
              </tr>
            ))}
            {!entries.length && <tr><td colSpan={6} className="px-3 py-6 text-center text-gray-400">No functions match this search.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
