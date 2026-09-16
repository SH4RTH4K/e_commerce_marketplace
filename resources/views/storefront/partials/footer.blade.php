@php
  $site = site_name();
  $facebook = setting('facebook_url');
  $instagram = setting('instagram_url');
  $twitter = setting('twitter_url');
  $youtube = setting('youtube_url');
  $footerText = trim((string) setting('footer_text', ''));

  $paymentBadges = [];
  if ((string) setting('pay_bkash_enabled', '1') === '1' && setting('bkash_number')) {
      $paymentBadges[] = 'bKash';
  }
  if ((string) setting('pay_nagad_enabled', '1') === '1' && setting('nagad_number')) {
      $paymentBadges[] = 'Nagad';
  }
  if ((string) setting('pay_rocket_enabled', '1') === '1' && setting('rocket_number')) {
      $paymentBadges[] = 'Rocket';
  }
  if ((string) setting('show_cards_in_footer', '0') === '1') {
      array_unshift($paymentBadges, 'VISA', 'Mastercard');
  }
@endphp
<footer class="bg-white border-t border-slate-100 mt-14">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-10">
      <div class="lg:col-span-2">
        @include('partials.brand')
        @if($footerText !== '')
          <p class="mt-4 max-w-xs text-sm text-slate-500">{{ $footerText }}</p>
        @endif
        @if($facebook || $instagram || $twitter || $youtube)
          <div class="mt-5 flex gap-3">
            @if($facebook)
              <a href="{{ $facebook }}" target="_blank" rel="noopener" class="grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-brand-500 hover:text-white transition" aria-label="Facebook">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7A10 10 0 0 0 22 12z"/></svg>
              </a>
            @endif
            @if($instagram)
              <a href="{{ $instagram }}" target="_blank" rel="noopener" class="grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-brand-500 hover:text-white transition" aria-label="Instagram">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1" fill="currentColor" stroke="none"/></svg>
              </a>
            @endif
            @if($twitter)
              <a href="{{ $twitter }}" target="_blank" rel="noopener" class="grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-brand-500 hover:text-white transition" aria-label="X (Twitter)">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2H21.5l-7.5 8.57L22.5 22h-6.59l-5.16-6.74L5.2 22H1.94l8.03-9.17L1.5 2h6.75l4.66 6.18L18.244 2Zm-1.16 18.1h1.83L7.05 3.79H5.09L17.084 20.1Z"/></svg>
              </a>
            @endif
            @if($youtube)
              <a href="{{ $youtube }}" target="_blank" rel="noopener" class="grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-brand-500 hover:text-white transition" aria-label="YouTube">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2C0 8.1 0 12 0 12s0 3.9.5 5.8a3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1C24 15.9 24 12 24 12s0-3.9-.5-5.8ZM9.6 15.6V8.4l6.3 3.6-6.3 3.6Z"/></svg>
              </a>
            @endif
          </div>
        @endif
      </div>
      <div>
        <h3 class="text-sm font-bold">Customer Care</h3>
        <ul class="mt-4 space-y-3 text-sm text-slate-500">
          <li><a href="{{ route('contact') }}" class="hover:text-brand-600">Contact Us</a></li>
          <li><a href="{{ route('track') }}" class="hover:text-brand-600">Track Order</a></li>
          <li><a href="{{ route('terms') }}" class="hover:text-brand-600">Terms of Service</a></li>
          <li><a href="{{ route('privacy') }}" class="hover:text-brand-600">Privacy Policy</a></li>
        </ul>
      </div>
      <div>
        <h3 class="text-sm font-bold">{{ $site }}</h3>
        <ul class="mt-4 space-y-3 text-sm text-slate-500">
          <li><a href="{{ route('shop') }}" class="hover:text-brand-600">Shop</a></li>
          @if($hasFlashSale ?? false)
            <li><a href="{{ route('shop', ['flash' => 1]) }}" class="hover:text-brand-600">{{ setting('home_hot_deal_title', 'Flash Sale') }}</a></li>
          @endif
          <li><a href="{{ route('contact') }}" class="hover:text-brand-600">Help &amp; Support</a></li>
        </ul>
      </div>
    </div>
    <div class="mt-12 pt-8 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
      <p class="text-sm text-slate-500">© {{ date('Y') }} {{ $site }}. All rights reserved.</p>
      <div class="flex items-center gap-2 flex-wrap justify-center">
        @foreach($paymentBadges as $badge)
          <span class="rounded bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-500">{{ $badge }}</span>
        @endforeach
      </div>
    </div>
  </div>

</footer>
