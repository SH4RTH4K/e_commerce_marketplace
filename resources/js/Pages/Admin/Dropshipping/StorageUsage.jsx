import DropshippingSubpage from './Subpage';

export default function StorageUsage({ suppliers = [], products = 0, variants = 0, categories = 0 }) {
  return <DropshippingSubpage title="Storage Usage" description="Usage of the local supplier mirror and integration metadata tables.">
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">{[['Products', products], ['Variants', variants], ['Categories', categories], ['Suppliers', suppliers.length]].map(([label, value]) => <div key={label} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-5"><p className="text-xs uppercase tracking-wider text-gray-400">{label}</p><p className="text-3xl font-bold text-gray-900 mt-2">{value}</p></div>)}</div>
    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden"><div className="px-5 py-4 border-b border-gray-100"><h2 className="font-bold text-gray-900">Supplier storage breakdown</h2></div><table className="w-full text-sm"><tbody className="divide-y divide-gray-50">{suppliers.map(supplier => <tr key={supplier.id}><td className="px-5 py-3 font-semibold text-gray-700">{supplier.name}</td><td className="px-5 py-3 text-xs text-gray-500">{supplier.products_count} products · {supplier.categories_count} categories</td></tr>)}{suppliers.length === 0 && <tr><td className="px-5 py-8 text-center text-sm text-gray-400">No suppliers configured.</td></tr>}</tbody></table></div>
  </DropshippingSubpage>;
}
