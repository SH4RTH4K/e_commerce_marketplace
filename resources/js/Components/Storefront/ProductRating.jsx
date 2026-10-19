export default function ProductRating({ rating, reviewCount, className = '' }) {
  const count = Math.max(0, Number(reviewCount) || 0);
  if (count === 0) {
    return <span className="text-sm text-gray-500">No reviews yet</span>;
  }

  const stars = Math.max(0, Math.min(5, Math.round(Number(rating) || 0)));
  return (
    <div className={`flex items-center gap-1 ${className}`} aria-label={`${stars} out of 5 stars from ${count} reviews`}>
      <span aria-hidden="true" className="text-amber-400">{'★'.repeat(stars)}<span className="text-gray-200">{'★'.repeat(5 - stars)}</span></span>
      <span className="text-sm text-gray-500">({count})</span>
    </div>
  );
}
