import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function CategoryOrdering({ categories }) {
  const { data, setData, post, processing } = useForm({
    order: categories.map(c => ({ id: c.id, menu_order: c.menu_order }))
  });

  const [localOrder, setLocalOrder] = useState(data.order);

  const handlePositionChange = (id, newPosition) => {
    const updated = localOrder.map(item => 
      item.id === id ? { ...item, menu_order: parseInt(newPosition) || 0 } : item
    );
    setLocalOrder(updated);
    setData('order', updated);
  };

  const submit = (e) => {
    e.preventDefault();
    post('/admin/categories/ordering');
  };

  return (
    <>
      <Head title="Menu Ordering" />
      <AdminLayout title="Menu Ordering">
        <div className="max-w-4xl space-y-5">
          <div className="flex items-center justify-between">
            <a href="/admin/categories" className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
              Back to Categories
            </a>
          </div>

          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
              <h3 className="font-semibold text-gray-900">Navbar & Menu Ordering</h3>
              <p className="text-sm text-gray-500 mt-1">
                Enter priority numbers (e.g., 10, 20, 30...) to order the categories in the storefront menu. Categories with lower numbers appear first.
              </p>
            </div>
            
            <form onSubmit={submit}>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-white border-b border-gray-100">
                      <th className="px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left">Priority</th>
                      <th className="px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left">Category</th>
                      <th className="px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-left">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-50">
                    {categories.map(category => {
                      const currentVal = localOrder.find(o => o.id === category.id)?.menu_order ?? 0;
                      return (
                        <tr key={category.id} className="hover:bg-gray-50/50 transition-colors">
                          <td className="px-5 py-3 w-32">
                            <input 
                              type="number" 
                              min="0"
                              value={currentVal}
                              onChange={e => handlePositionChange(category.id, e.target.value)}
                              className="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300" 
                            />
                          </td>
                          <td className="px-5 py-3 font-medium text-gray-800">
                            {category.name}
                          </td>
                          <td className="px-5 py-3">
                            <div className="flex flex-col gap-1">
                              {!category.is_active && (
                                <span className="text-xs text-red-500 font-medium">Inactive (Hidden everywhere)</span>
                              )}
                              {category.is_active && !category.show_in_menu && (
                                <span className="text-xs text-orange-500 font-medium">Hidden in Menus</span>
                              )}
                              {category.is_active && category.show_in_menu && (
                                <span className="text-xs text-green-600 font-medium">Visible</span>
                              )}
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
              
              <div className="p-5 border-t border-gray-100 bg-gray-50 flex items-center justify-end">
                <button 
                  type="submit" 
                  disabled={processing} 
                  className="px-6 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white font-semibold rounded-xl transition-colors"
                >
                  {processing ? 'Saving...' : 'Save Ordering'}
                </button>
              </div>
            </form>
          </div>
        </div>
      </AdminLayout>
    </>
  );
}
