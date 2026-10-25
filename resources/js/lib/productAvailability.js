export const sourcingMessage = 'We source this item after receiving your order. Availability and delivery timing will be confirmed before dispatch.';

export function productAvailability(product = {}) {
  product = product || {};
  const stock = Math.max(0, Number(product.available_stock_quantity ?? product.stock_quantity) || 0);
  const backorder = Boolean(product.allow_backorder);
  const canOrder = product.can_order ?? (backorder || stock > 0);
  const label = product.availability_label || (stock > 0 ? 'In Stock' : backorder ? 'Available to order' : 'Out of Stock');
  return { stock, canOrder, backorder, hideQuantity: stock <= 0 && (backorder || Boolean(product.hide_stock_quantity)), label };
}

export function orderQuantityLimit(product = {}, maximum = 100000) {
  return product.allow_backorder ? maximum : Math.min(productAvailability(product).stock, maximum);
}

export function requiresSourcing(product, quantity = 1) {
  const { backorder, stock } = productAvailability(product);
  return backorder && Number(quantity) > stock;
}
