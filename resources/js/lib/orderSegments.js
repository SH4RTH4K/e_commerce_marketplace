export const orderSegments = [
  { key: 'all', label: 'All Orders', description: 'Every customer order, across all fulfilment stages.' },
  { key: 'pending', label: 'Pending', description: 'New orders awaiting confirmation. Review the customer, products, and delivery details.' },
  { key: 'confirmed', label: 'Confirmed', description: 'Accepted orders ready for packing and preparation.' },
  { key: 'processing', label: 'Processing', description: 'Orders being prepared for dispatch.' },
  { key: 'shipped', label: 'Shipped', description: 'Dispatched orders awaiting delivery. Follow the courier tracking details.' },
  { key: 'delivered', label: 'Delivered', description: 'Completed orders delivered to the customer.' },
  { key: 'cancelled', label: 'Cancelled', description: 'Orders closed without delivery.' },
  { key: 'pending_verification', label: 'Payment Verification', description: 'Active bKash, Nagad, and Rocket orders awaiting payment review. Cash on delivery is excluded.' },
];

export function activeOrderSegment(url) {
  const parsed = new URL(url, 'http://localhost');
  if (/^\/admin\/orders\/\d+\/?$/.test(parsed.pathname)) return activeOrderSegment(orderReturnUrl(url));
  if (!/^\/admin\/orders\/?$/.test(parsed.pathname)) return null;
  const status = parsed.searchParams.get('status') || 'all';
  return orderSegments.some(segment => segment.key === status) ? status : 'all';
}

export function orderSegmentHref(key) {
  return key === 'all' ? '/admin/orders' : `/admin/orders?status=${key}`;
}

function safeOrderListUrl(url) {
  return typeof url === 'string' && /^\/admin\/orders(?:\?[^#]*)?$/.test(url) ? url : '/admin/orders';
}

export function orderDetailHref(id, listUrl, ship = false) {
  return `/admin/orders/${id}?return_to=${encodeURIComponent(safeOrderListUrl(listUrl))}${ship ? '&ship=1' : ''}`;
}

export function orderReturnUrl(url) {
  return safeOrderListUrl(new URL(url, 'http://localhost').searchParams.get('return_to'));
}
