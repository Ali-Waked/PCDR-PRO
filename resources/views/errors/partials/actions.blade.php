@foreach ($actions as $action)
    @if ($action['type'] === 'link')
        <a class="pcdr-error__button pcdr-error__button--{{ $action['variant'] }}" href="{{ $action['href'] }}">
            @switch($action['icon'])
                @case('home')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M3 10.5 12 3l9 7.5"/>
                        <path d="M5.5 9.5V20h13V9.5"/>
                        <path d="M9.5 20v-6h5v6"/>
                    </svg>
                    @break
                @case('contact')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                        <path d="m3.5 6.5 8.5 6 8.5-6"/>
                    </svg>
                    @break
            @endswitch
            <span>{{ $action['label'] }}</span>
        </a>
    @else
        <button
            type="button"
            class="pcdr-error__button pcdr-error__button--{{ $action['variant'] }}"
            data-error-action="{{ $action['type'] }}"
            @if ($action['type'] === 'back') hidden @endif
        >
            @switch($action['icon'])
                @case('back')
                    <svg class="directional-flip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M10 5 3 12l7 7"/>
                        <path d="M3 12h11a6 6 0 0 1 6 6"/>
                    </svg>
                    @break
                @case('reload')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M20 11a8 8 0 1 0-2.3 6.1"/>
                        <path d="M20 4v7h-7"/>
                    </svg>
                    @break
            @endswitch
            <span>{{ $action['label'] }}</span>
        </button>
    @endif
@endforeach
