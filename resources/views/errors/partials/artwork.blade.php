@switch($code)
    @case(404)
        <svg viewBox="0 0 520 460" preserveAspectRatio="xMidYMid meet" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <circle cx="360" cy="190" r="150" fill="#EAF5F4"/>
            <path d="M46 350h428" stroke="#DAC3A3" stroke-width="2"/>
            <path d="M62 326c68 0 77-54 145-54 67 0 76-72 143-72 44 0 76-27 108-70" stroke="#3E5172" stroke-width="3" stroke-linecap="round" stroke-dasharray="7 11"/>
            <circle cx="62" cy="326" r="9" fill="#397F7B"/>
            <circle cx="207" cy="272" r="9" fill="#FFFFFF" stroke="#397F7B" stroke-width="4"/>
            <circle cx="350" cy="200" r="9" fill="#397F7B"/>
            <path d="m382 129 22-22m-22 0h22v22" stroke="#397F7B" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M86 380h230M86 398h150" stroke="#E9EDF2" stroke-width="8" stroke-linecap="round"/>
            <text class="pcdr-error__code" x="50%" y="50%" text-anchor="middle" dominant-baseline="central">{{ $code }}</text>
        </svg>
        @break
    @case(403)
        <svg viewBox="0 0 520 460" preserveAspectRatio="xMidYMid meet" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <circle cx="155" cy="150" r="120" fill="#EAF5F4"/>
            <rect x="160" y="62" width="220" height="300" rx="18" fill="#FFFFFF" stroke="#25334B" stroke-width="2"/>
            <path d="M198 112h112M198 142h84" stroke="#E9EDF2" stroke-width="8" stroke-linecap="round"/>
            <path d="M270 196 334 222v54c0 40-27 66-64 80-37-14-64-40-64-80v-54l64-26Z" fill="#EAF5F4" stroke="#397F7B" stroke-width="2"/>
            <path d="m238 274 22 22 44-48" stroke="#397F7B" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M104 366h312" stroke="#DAC3A3" stroke-width="2"/>
            <circle cx="420" cy="78" r="12" fill="#DAC3A3"/>
            <text class="pcdr-error__code" x="50%" y="50%" text-anchor="middle" dominant-baseline="central">{{ $code }}</text>
        </svg>
        @break
    @default
        <svg viewBox="0 0 520 460" preserveAspectRatio="xMidYMid meet" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <circle cx="320" cy="210" r="150" fill="#EAF5F4"/>
            <path d="M56 296c72-98 128 58 200-42s128-80 208 8" stroke="#3E5172" stroke-width="14" stroke-linecap="round"/>
            <path d="M64 232c76 92 132-56 208 32s126 76 184-6" stroke="#71BCB7" stroke-width="12" stroke-linecap="round"/>
            <path d="M94 348h330" stroke="#DAC3A3" stroke-width="6" stroke-linecap="round"/>
            <circle cx="94" cy="348" r="7" fill="#397F7B"/>
            <circle cx="424" cy="348" r="7" fill="#397F7B"/>
            <path d="M146 112h44m-22-22v44" stroke="#D7C5A1" stroke-width="3" stroke-linecap="round"/>
            <text class="pcdr-error__code" x="50%" y="50%" text-anchor="middle" dominant-baseline="central">{{ $code }}</text>
        </svg>
@endswitch
