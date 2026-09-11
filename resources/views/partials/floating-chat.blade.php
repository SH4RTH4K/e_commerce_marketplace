@php
  // Controlled from Admin → Settings (WhatsApp number / Messenger page).
  $rawWa = trim((string) (setting('whatsapp_number') ?: setting('contact_phone', '')));
  $whatsapp = preg_replace('/\D+/', '', $rawWa) ?: '';
  if ($whatsapp !== '' && str_starts_with($whatsapp, '0') && strlen($whatsapp) === 11) {
      $whatsapp = '88' . $whatsapp;
  }
  if ($whatsapp === '') {
      $whatsapp = '8801700000000';
  }

  $messenger = trim((string) setting('messenger_page', 'sharthak.page'));
  $messenger = ltrim($messenger !== '' ? $messenger : 'sharthak.page', '/@');
@endphp

<div id="floatingChat" class="floating-chat" aria-live="polite">
  <div id="floatingChatActions" class="floating-chat__actions" aria-hidden="true">
    <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" class="floating-chat__btn floating-chat__btn--whatsapp" aria-label="Chat on WhatsApp">
      <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.7 15l-1.3 5 5.1-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.2 1.2-1.7 1.2-.4 0-.9.2-3-.9-2.6-1.3-4.2-4-4.3-4.2-.1-.2-1-1.3-1-2.5s.6-1.8.9-2c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6c-.2.2-.3.4-.1.7.5.8 1 1.2 1.7 1.7.3.2.5.2.7-.1l.6-.7c.2-.2.4-.2.6-.1l1.9.9c.2.1.4.2.4.3.1.3.1.8-.1 1.4Z"/></svg>
    </a>
    <a href="https://m.me/{{ $messenger }}" target="_blank" rel="noopener noreferrer" class="floating-chat__btn floating-chat__btn--messenger" aria-label="Chat on Messenger">
      <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.48 2 2 6.13 2 11.24c0 2.9 1.44 5.49 3.69 7.18V22l3.37-1.85c.9.25 1.86.39 2.94.39 5.52 0 10-4.13 10-9.24S17.52 2 12 2Zm5.8 6.8-2.2 2.33c-.42.45-1.1.45-1.52 0l-1.58-1.67a1.08 1.08 0 0 0-1.52 0l-2.83 3a.72.72 0 0 0 1.02 1.02l2.24-2.38 1.58 1.67c.42.45 1.1.45 1.52 0l2.73-2.9a.72.72 0 0 0-1.02-1.02Z"/></svg>
    </a>
  </div>
  <button type="button" id="floatingChatTrigger" class="floating-chat__trigger" aria-label="Contact us" aria-expanded="false" aria-controls="floatingChatActions">
    <svg class="floating-chat__icon floating-chat__icon--open h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 0 1-4-.8L3 21l1.8-4.2A8.8 8.8 0 0 1 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z"/></svg>
    <svg class="floating-chat__icon floating-chat__icon--close h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
  </button>
</div>
