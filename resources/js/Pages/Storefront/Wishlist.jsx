import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import ProductCard from '@/Components/Storefront/ProductCard';

export default function Wishlist() {
  const [wishlist, setWishlist] = useState([]);
  
  useEffect(() => {
    const saved = localStorage.getItem('sharthak_wishlist');
    if (saved) {
      try {
        setWishlist(JSON.parse(saved));
      } catch (e) {
        console.error('Failed to parse wishlist', e);
      }
    }
    
    const handleUpdate = () => {
      const updated = localStorage.getItem('sharthak_wishlist');
      setWishlist(updated ? JSON.parse(updated) : []);
    };
    
    window.addEventListener('wishlist-updated', handleUpdate);
    return () => window.removeEventListener('wishlist-updated', handleUpdate);
  }, []);

  const clearWishlist = () => {
    if (confirm('Are you sure you want to clear your wishlist?')) {
      localStorage.removeItem('sharthak_wishlist');
      setWishlist([]);
      window.dispatchEvent(new Event('wishlist-updated'));
    }
  };

  return (
    <StorefrontLayout>
      <Head title="My Wishlist" />
      <div className="max-w-7xl mx-auto px-4 py-8 md:py-12">
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 mb-8">
          <h1 className="text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight">My Wishlist</h1>
          {wishlist.length > 0 && (
            <button 
              onClick={clearWishlist}
              className="text-sm font-semibold text-red-500 hover:text-red-700 hover:underline"
            >
              Clear Wishlist
            </button>
          )}
        </div>
        
        {wishlist.length > 0 ? (
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-4 md:gap-5">
            {wishlist.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        ) : (
          <div className="text-center py-16 bg-white rounded-2xl shadow-sm border border-gray-100">
            <span className="text-5xl mb-4 block">💖</span>
            <h2 className="text-xl font-bold text-gray-900 mb-2">Your wishlist is empty</h2>
            <p className="text-gray-500 mb-6 max-w-md mx-auto">Looks like you haven't added any products to your wishlist yet. Explore our store to find your favorites!</p>
            <Link href="/shop" className="inline-block bg-[#f15a24] text-white font-bold py-3 px-8 rounded-xl shadow hover:bg-[#d94b1a] transition-colors">
              Continue Shopping
            </Link>
          </div>
        )}
      </div>
    </StorefrontLayout>
  );
}
