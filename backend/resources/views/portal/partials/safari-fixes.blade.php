{{-- Safari / macOS desktop hardening shared by every portal layout. --}}
<style>
    html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }

    /* Safari ignores border-radius clipping on overflow:hidden boxes when a child is transformed
       (hover zoom on card images) — isolating the stacking context restores the rounded corners. */
    [class*="overflow-hidden"][class*="rounded"] { isolation: isolate; }

    /* Keep tap/click targets from showing the grey WebKit highlight and make buttons inherit fonts. */
    button, [role="button"], summary { -webkit-tap-highlight-color: transparent; }
    button { font-family: inherit; }

</style>
