export const customerSegments = [
  { key: 'all', label: 'All Customers', note: 'Registered accounts and guest buyers' },
  { key: 'registered', label: 'Registered', note: 'Customers with a sign-in account' },
  { key: 'guest', label: 'Guest Buyers', note: 'Orders without a customer account' },
  { key: 'repeat', label: 'Repeat Buyers', note: 'At least 2 delivered orders' },
  { key: 'no_orders', label: 'No Orders Yet', note: 'Registered, with no placed orders' },
  { key: 'inactive', label: 'Inactive Accounts', note: 'Account activation is required' },
];

export function safeCustomerReturn(url) {
  return typeof url === 'string' && /^\/admin\/customers(?:\/profiles\/(?:account|phone|order)-[0-9]+)?(?:\?[^#\u0000-\u001f\u007f]*)?$/.test(url) ? url : '/admin/customers';
}

export function activeCustomerSegment(url) {
  const parsed = new URL(url, 'http://localhost');
  if (!/^\/admin\/customers(?:\/|$)/.test(parsed.pathname)) return null;
  if (parsed.pathname !== '/admin/customers') {
    const returnTo = safeCustomerReturn(parsed.searchParams.get('return_to'));
    const list = new URL(returnTo, 'http://localhost');
    return customerSegments.find(segment => segment.key === list.searchParams.get('segment'))?.key || 'all';
  }
  return customerSegments.find(segment => segment.key === parsed.searchParams.get('segment'))?.key || 'all';
}

export function customerProfileHref(customer, listUrl) {
  return `${customer.profile_url}?return_to=${encodeURIComponent(safeCustomerReturn(listUrl))}`;
}
