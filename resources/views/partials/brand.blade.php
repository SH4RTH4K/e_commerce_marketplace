@php
    $name = $siteName ?? site_name();
    $href = $href ?? route('home');
    $light = $light ?? false;
    $size = $size ?? 'md';
    $class = $class ?? '';
    $iconOnly = $iconOnly ?? false;
    $custom = has_custom_logo();

    $iconClass = match ($size) {
        'sm' => 'h-8 w-8',
        'lg' => 'h-11 w-11',
        default => 'h-9 w-9',
    };
    $textClass = match ($size) {
        'sm' => 'text-lg',
        'lg' => 'text-2xl',
        default => 'text-xl sm:text-2xl',
    };

    $isSharthak = strcasecmp((string) $name, 'SHARTHAK') === 0;
@endphp

<a href="{{ $href }}" class="flex items-center gap-2 shrink-0 min-w-0 {{ $class }}" aria-label="{{ $name }}">
  <img src="{{ logo_url() }}" alt="" class="{{ $iconClass }} object-contain shrink-0" />
  @unless($iconOnly)
    <span class="font-display font-extrabold tracking-tight {{ $textClass }} truncate leading-none {{ $light ? 'text-white' : 'text-ink' }}">
      {{ $isSharthak ? 'SHARTHAK' : $name }}
    </span>
  @endunless
</a>
