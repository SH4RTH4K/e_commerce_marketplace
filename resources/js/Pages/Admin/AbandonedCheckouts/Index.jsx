import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AbandonedCheckoutsIndex({ checkouts, stats, filter = 'active' }) {
  const { props } = usePage();
  const appName = props.app?.name || 'our store';
  const [notes, setNotes] = useState({});
  const changeFilter = value => router.get(`/admin/abandoned-checkouts${value !== 'all' ? `?filter=${value}` : ''}`, {}, { preserveScroll: true });

  const handleNoteChange = (id, value) => {
    setNotes({ ...notes, [id]: value });
  };

  const saveNote = (id) => {
    router.patch(`/admin/abandoned-checkouts/${id}/note`, { admin_note: notes[id] }, {
      preserveScroll: true
    });
  };

  const handleCreateOrder = (item) => {
    window.showConfirm(
      `Confirm & create order for "${item.customer_name || 'Customer'}"? This will automatically create an active order in the Orders list and mark this abandoned cart as recovered.`,
      () => {
        router.post(`/admin/abandoned-checkouts/${item.id}/create-order`);
      }
    );
  };

  return (
    <AdminLayout title="Abandoned Checkouts">
      <Head title="Abandoned Checkouts" />

      <div className="mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-black text-gray-900 tracking-tight">Abandoned Checkouts</h1>
          <p className="text-sm text-gray-500 mt-1">Customers who filled their details but didn't place an order.</p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <p className="text-sm text-gray-500 font-medium mb-1">Abandoned Carts</p>
          <p className="text-2xl font-black text-gray-900">{stats.total_abandoned}</p>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <p className="text-sm text-gray-500 font-medium mb-1">Lost Value</p>
          <p className="text-2xl font-black text-orange-600">৳{Number(stats.total_abandoned_amount).toLocaleString()}</p>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <p className="text-sm text-gray-500 font-medium mb-1">Recovered Carts</p>
          <p className="text-2xl font-black text-gray-900">{stats.total_recovered}</p>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <p className="text-sm text-gray-500 font-medium mb-1">Recovered Value</p>
          <p className="text-2xl font-black text-green-600">৳{Number(stats.total_recovered_amount).toLocaleString()}</p>
        </div>
      </div>

      <div className="mb-4 flex items-center gap-2">
        <button type="button" onClick={() => changeFilter('all')} className={`px-4 py-2 rounded-xl text-sm font-semibold ${filter === 'all' ? 'bg-gray-800 text-white' : 'bg-white border border-gray-200 text-gray-600'}`}>All carts</button>
        <button type="button" onClick={() => changeFilter('active')} className={`px-4 py-2 rounded-xl text-sm font-semibold ${filter === 'active' ? 'bg-orange-500 text-white' : 'bg-white border border-gray-200 text-gray-600'}`}>Active carts</button>
        <button type="button" onClick={() => changeFilter('recovered')} className={`px-4 py-2 rounded-xl text-sm font-semibold ${filter === 'recovered' ? 'bg-green-600 text-white' : 'bg-white border border-gray-200 text-gray-600'}`}>Recovered carts ({stats.total_recovered})</button>
      </div>

      <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-gray-50/50 border-b border-gray-100">
                <th className="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Customer</th>
                <th className="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Contact</th>
                <th className="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Address</th>
                <th className="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cart Details</th>
                <th className="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th className="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Last Active</th>
                <th className="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {checkouts.data.length === 0 ? (
                <tr>
                  <td colSpan="7" className="p-8 text-center text-gray-400">
                    <span className="block text-4xl mb-2">🛒</span>
                    <p>No abandoned checkouts found.</p>
                  </td>
                </tr>
              ) : checkouts.data.map((item) => (
                <tr key={item.id} className="hover:bg-gray-50/50 transition-colors">
                  <td className="p-4">
                    <span className="font-bold text-gray-900">{item.customer_name || 'N/A'}</span>
                    <div className="text-xs text-gray-500 mt-1">
                      {item.cart_items && item.cart_items.length > 0 ? (
                        <span className="inline-flex items-center gap-1">
                          <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                          {item.cart_items.length} item(s)
                        </span>
                      ) : 'No items'}
                    </div>
                  </td>
                  <td className="p-4 align-top">
                    {item.customer_phone ? (
                      <div className="text-sm font-semibold text-gray-900 mb-0.5">{item.customer_phone}</div>
                    ) : null}
                    {item.customer_email ? (
                      <div className="text-xs text-gray-500">{item.customer_email}</div>
                    ) : null}
                  </td>
                  <td className="p-4 align-top"><span className={`inline-flex px-2 py-1 rounded-full text-xs font-semibold ${item.is_recovered ? 'bg-green-50 text-green-700' : 'bg-orange-50 text-orange-700'}`}>{item.is_recovered ? 'Recovered' : 'Active'}</span></td>
                  <td className="p-4 align-top">
                    <div className="text-sm text-gray-600 max-w-xs truncate" title={item.shipping_address}>
                      {item.shipping_address || '-'}
                    </div>
                  </td>
                  <td className="p-4 align-top">
                    <div className="font-bold text-gray-900 mb-1">৳{Number(item.cart_total).toLocaleString()}</div>
                    <div className="text-xs text-gray-500 max-w-[220px] space-y-0.5">
                      {item.cart_items && item.cart_items.map((cartItem, idx) => (
                        <div key={idx} className="truncate" title={cartItem.name}>
                          {cartItem.qty || cartItem.quantity || 1}x {cartItem.name}
                        </div>
                      ))}
                    </div>
                  </td>
                  <td className="p-4 align-top">
                    <span className="text-xs text-gray-500 block mb-2 font-medium">
                      {new Date(item.last_active_at).toLocaleString()}
                    </span>
                    <div className="flex flex-col gap-1.5 w-48">
                      <textarea 
                        className="w-full text-xs border border-gray-200 rounded-lg p-2 focus:ring-1 focus:ring-orange-500 outline-none resize-none" 
                        rows="2" 
                        placeholder="Add admin note..."
                        value={notes[item.id] !== undefined ? notes[item.id] : (item.admin_note || '')}
                        onChange={(e) => handleNoteChange(item.id, e.target.value)}
                      ></textarea>
                      <button onClick={() => saveNote(item.id)} className="text-[11px] font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 py-1 rounded-lg transition-colors">
                        Save Note
                      </button>
                    </div>
                  </td>
                  <td className="p-4 text-right align-top whitespace-nowrap">
                    <div className="flex items-center justify-end gap-1.5">
                      {/* 1-Click Create Order & Recover Button */}
                      {!item.is_recovered && <button
                        onClick={() => handleCreateOrder(item)}
                        className="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold transition-all shadow-xs shadow-orange-200"
                        title="1-Click Create Order & Mark Recovered"
                      >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Create Order</span>
                      </button>}

                      {/* WhatsApp reachout */}
                      {item.customer_phone && (
                        <a
                          href={`https://wa.me/${item.customer_phone.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(`Hello ${item.customer_name || 'there'},\n\nWe noticed you left some items in your cart at ${appName}. Do you need any help completing your order?`)}`} 
                          target="_blank"
                          rel="noreferrer" 
                          className="inline-flex items-center justify-center p-2 rounded-xl bg-green-50 text-green-600 hover:bg-green-100 transition-colors"
                          title="WhatsApp Recovery Message"
                        >
                          <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                          </svg>
                        </a>
                      )}

                      {/* Simple Mark as Recovered */}
                      {!item.is_recovered && <button
                        onClick={() => window.showConfirm('Mark this cart as recovered without creating order?', () => router.patch(`/admin/abandoned-checkouts/${item.id}/recover`))}
                        className="inline-flex items-center justify-center p-2 rounded-xl bg-gray-50 text-gray-600 hover:bg-gray-100 transition-colors"
                        title="Mark as Recovered"
                      >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/></svg>
                      </button>}

                      {/* Delete */}
                      <button
                        onClick={() => window.showConfirm('Delete this abandoned cart record forever?', () => router.delete(`/admin/abandoned-checkouts/${item.id}`))}
                        className="inline-flex items-center justify-center p-2 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                        title="Delete Record"
                      >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {checkouts.links && checkouts.links.length > 3 && (
          <div className="p-4 border-t border-gray-50 flex flex-wrap gap-1.5 items-center justify-center">
            {checkouts.links.map((link, i) => (
              link.url ? (
                <button
                  key={i}
                  onClick={() => router.get(link.url)}
                  className={`min-w-[36px] h-9 px-3 rounded-xl text-sm font-medium transition-colors ${link.active ? 'bg-orange-500 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'}`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ) : (
                <span
                  key={i}
                  className="min-w-[36px] h-9 px-3 flex items-center justify-center rounded-xl text-sm text-gray-300"
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              )
            ))}
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
