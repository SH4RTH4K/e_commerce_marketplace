import { useState, useEffect } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { money, imageUrl } from '@/lib/utils';
import PulseHeart from './PulseHeart';

export default function ProductCard({ product }) {
  const { app, cartItems = [] } = usePage().props;
  const isTemplateOne = app?.settings?.storefront_template === 'template-1';
  const [imageError, setImageError] = useState(false);
  const [inWishlist, setInWishlist] = useState(false);
  const [wishlistCount, setWishlistCount] = useState(0);

  useEffect(() => {
    const checkWishlist = () => {
      const saved = localStorage.getItem('sharthak_wishlist');
      if (saved) {
        try {
          const list = JSON.parse(saved);
          const wishlist = Array.isArray(list) ? list : [];
          setInWishlist(wishlist.some(p => p.id === product.id));
          setWishlistCount(wishlist.length);
        } catch (e) {}
      } else {
        setInWishlist(false);
        setWishlistCount(0);
      }
    };
    checkWishlist();
    window.addEventListener('wishlist-updated', checkWishlist);
    return () => window.removeEventListener('wishlist-updated', checkWishlist);
  }, [product.id]);

  const toggleWishlist = (nextLiked) => {
    const saved = localStorage.getItem('sharthak_wishlist');
    let list = [];
    if (saved) {
      try {
        const parsed = JSON.parse(saved);
        list = Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        list = [];
      }
    }
    
    if (!nextLiked) {
      list = list.filter(p => p.id !== product.id);
    } else {
      if (!list.some(p => p.id === product.id)) list.push(product);
    }
    
    localStorage.setItem('sharthak_wishlist', JSON.stringify(list));
    setInWishlist(nextLiked);
    setWishlistCount(list.length);
    window.dispatchEvent(new Event('wishlist-updated'));
  };
  
  // Calculate discount percentage manually if not passed
  let discount = 0;
  if (product.sale_price && product.regular_price > product.sale_price) {
    discount = Math.round(((product.regular_price - product.sale_price) / product.regular_price) * 100);
  }

  const primaryImage = product.images?.find(img => img.is_primary)?.path || product.images?.[0]?.path;
  const imageSrc = imageUrl(primaryImage, product.name);
  const stock = Math.max(0, parseInt(product.stock_quantity) || 0);
  const rating = Math.max(0, Math.min(5, Math.round(parseFloat(product.rating) || 0)));
  const cardBg = app.settings?.product_card_bg_color || '#FFFFFF';
  const cardText = app.settings?.product_card_text_color || '#111827';
  const btnBg = app.settings?.product_card_btn_bg_color || '#f15a24';
  const btnTextColor = app.settings?.product_card_btn_text_color || '#ffffff';
  const alreadyOrdered = cartItems.some(item => Number(item.product_id) === Number(product.id));
  const buyText = alreadyOrdered ? 'Already Ordered' : (app.settings?.product_card_buy_text || 'Order Now');
  const hasVariants = Boolean(product.variants_exists);
  const inStock = stock > 0 || Boolean(product.variants_in_stock_exists);
  const buttonStyle = {
    backgroundColor: inStock ? btnBg : '#b7bfed',
    color: btnTextColor,
  };
  const buttonClassName = 'storefront-product-button w-full rounded-xl text-[11px] sm:text-[13px] font-bold py-2.5 sm:py-3 transition-all hover:brightness-95 active:scale-[0.99] flex items-center justify-center gap-1.5 shadow-sm text-center';
  const previewCart = (e) => {
    e.preventDefault();
    window.dispatchEvent(new Event('open-cart-drawer'));
  };

  return (
    <article 
      className={`product-card storefront-product-card relative flex flex-col h-full min-w-0 transition-all group ${isTemplateOne ? 'template-1-product-card' : 'rounded-[20px] border border-gray-100 shadow-sm p-2 hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)]'}`}
      style={{ backgroundColor: cardBg }}
    >
      <div className="relative shrink-0 aspect-square">
        <Link href={`/product/${product.slug || product.id}`} className="block w-full h-full overflow-hidden bg-gray-50 rounded-[14px]">
          {discount > 0 && (
            <span className="absolute left-2.5 top-2.5 z-10 bg-[#00D06C] text-white text-[10px] sm:text-[11px] font-bold px-2 py-0.5 rounded-md">
              -{discount}%
            </span>
          )}
          {!discount && product.is_free_shipping && (
            <span className="absolute left-2.5 top-2.5 z-10 bg-emerald-600 text-white text-[9px] sm:text-[10px] font-bold px-2 py-0.5 rounded-md shadow-xs">
              Free Delivery
            </span>
          )}
          {discount > 0 && product.is_free_shipping && (
            <span className="absolute left-2.5 bottom-2.5 z-10 bg-emerald-700 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-md shadow-xs">
              Free Delivery
            </span>
          )}
          {imageSrc && !imageError ? (
            <img src={imageSrc} onError={() => setImageError(true)} className="product-img w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt={product.name} loading="lazy" />
          ) : (
            <div className="product-img w-full h-full flex items-center justify-center text-gray-300">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            </div>
          )}
        </Link>
        <PulseHeart
          liked={inWishlist}
          count={wishlistCount}
          onChange={toggleWishlist}
          showCount
          icon="heart"
          idleOutline
          size={20}
          corner={32}
          likedColor="#ff4d6d"
          idleColor="#8b8b93"
          pillColor="#232326"
          textColor="#f5f5f5"
          duration={560}
          dotSize={0.3}
          overshoot={1.7}
          beat={3}
          rollDuration={350}
          disabled={false}
          label={inWishlist ? `Remove ${product.name} from wishlist` : `Add ${product.name} to wishlist`}
          className="product-card-pulse-heart absolute right-2.5 top-2.5 z-10"
        />
      </div>
      
      <div className="p-3 sm:p-4 flex-1 flex flex-col min-w-0">
        <span className="product-card-category text-[9px] sm:text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 block">
          {product.category?.name || 'Category'}
        </span>
        
        <div className="flex items-start justify-between gap-3 mb-4">
          <Link 
            href={`/product/${product.slug || product.id}`} 
            className="storefront-product-title block text-[13px] sm:text-[14px] hover:opacity-80 font-bold transition-colors leading-tight line-clamp-2 pr-2"
            style={{ color: cardText }}
          >
            {product.name}
          </Link>
          
          <div className="flex flex-col items-end shrink-0 leading-tight mt-0.5">
            <span className="storefront-product-price whitespace-nowrap font-bold text-gray-900 text-[13px] sm:text-[14px]">
              {money(product.sale_price || product.regular_price)}
            </span>
            {product.sale_price && product.regular_price > product.sale_price && (
              <span className="storefront-product-compare-price whitespace-nowrap text-[10px] sm:text-[11px] text-gray-400 line-through mt-1">
                {money(product.regular_price)}
              </span>
            )}
          </div>
        </div>
        
        <div className="mt-auto flex items-center">
          {hasVariants && inStock ? (
            <Link 
              href={`/product/${product.slug || product.id}`}
              onClick={alreadyOrdered ? previewCart : undefined}
              style={buttonStyle}
              className={buttonClassName}
            >
              <svg className="w-4 h-4 sm:w-[18px] sm:h-[18px] shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
              <span>{inStock ? buyText : 'Out of Stock'}</span>
            </Link>
          ) : (
            <button 
              type="button"
              disabled={!inStock}
              onClick={(e) => {
                 e.preventDefault();
                 if (alreadyOrdered) {
                   previewCart(e);
                   return;
                 }
                 router.post('/cart/add', {
                     product_id: product.id,
                     qty: 1
                 }, { preserveScroll: true });
              }}
              style={buttonStyle}
              className={`${buttonClassName} disabled:hover:brightness-100 disabled:active:scale-100 disabled:cursor-not-allowed cursor-pointer`}
            >
              <svg className="w-4 h-4 sm:w-[18px] sm:h-[18px] shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
              <span>{inStock ? buyText : 'Out of Stock'}</span>
            </button>
          )}
        </div>
      </div>
    </article>
  );
}
