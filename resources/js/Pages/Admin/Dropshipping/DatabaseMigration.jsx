import DropshippingSubpage from './Subpage';

export default function DatabaseMigration({ tables = [] }) {
  return <DropshippingSubpage title="Database Migration" description="Verify the additive dropship_* tables without changing existing commerce tables.">
    <div className="bg-blue-50 border border-blue-200 rounded-2xl p-4 text-sm text-blue-800">Migrations are executed through Laravel deployment commands. This page is read-only so an admin cannot accidentally alter production schema.</div>
    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden"><div className="px-5 py-4 border-b border-gray-100"><h2 className="font-bold text-gray-900">Integration tables</h2></div><table className="w-full text-sm"><tbody className="divide-y divide-gray-50">{tables.map(item => <tr key={item.table}><td className="px-5 py-3 font-mono text-xs text-gray-700">{item.table}</td><td className="px-5 py-3 text-right"><span className={`text-xs font-semibold ${item.exists ? 'text-green-700' : 'text-red-700'}`}>{item.exists ? 'Ready' : 'Missing'}</span></td></tr>)}</tbody></table></div>
  </DropshippingSubpage>;
}
