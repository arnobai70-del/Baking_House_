{{-- Self-hosted Noto Sans (D1). Latin @font-face rules live in tokens.css (no extra blocking request).
     Preloads only on Bangla pages, chosen from alternating-round lab measurements (docs/qa-phase9.5.md §3):
     there they remove the late Bengali swap (mobile CLS 0.18 → 0); on English pages a preload delayed first-visit
     LCP while `font-display: optional` already shows Noto Sans from the second page view (cached). --}}
@if (app()->getLocale() === 'bn')
    <link rel="preload" href="{{ asset('fonts/noto-sans-latin-v42.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/noto-sans-bengali-v33.woff2') }}" as="font" type="font/woff2" crossorigin>
    <style>
        /* Noto Sans Bengali, Bengali script subset, SIL Open Font License 1.1 (public/fonts/OFL-NotoSansBengali.txt) */
        @font-face { font-family: "Noto Sans Bengali"; font-style: normal; font-weight: 400 700; font-display: swap; src: url("{{ asset('fonts/noto-sans-bengali-v33.woff2') }}") format("woff2"); unicode-range: U+0951-0952, U+0964-0965, U+0980-09FE, U+1CD0, U+1CD2, U+1CD5-1CD6, U+1CD8, U+1CE1, U+1CEA, U+1CED, U+1CF2, U+1CF5-1CF7, U+200C-200D, U+20B9, U+25CC, U+A8F1, U+11DF0-11DF1; }
    </style>
@endif
