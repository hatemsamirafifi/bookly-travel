<style id="bookly-admin-theme">
    :root {
        --bookly-navy: #0A2540;
        --bookly-gold: #FFB800;
        --bookly-surface: #FFFFFF;
        --bookly-background: #F7F9FB;
        --bookly-border: #E2E8F0;
        --bookly-focus: #B76E00;
    }

    html:not(.dark) .fi-body { background: var(--bookly-background); color: var(--bookly-navy); }
    html:not(.dark) .fi-sidebar-header,
    html:not(.dark) .fi-topbar { background: var(--bookly-surface); border-bottom: 1px solid var(--bookly-border); }
    html:not(.dark) .fi-section,
    html:not(.dark) .fi-ta-ctn { border-radius: .75rem; box-shadow: 0 4px 24px -4px rgb(10 37 64 / .12); }
    .dark .bookly-admin-logo { color: #FFFFFF !important; }
    .fi-body :focus-visible { outline: 2px solid var(--bookly-focus); outline-offset: 2px; }

    @media (prefers-reduced-motion: reduce) {
        .fi-body *, .fi-body *::before, .fi-body *::after { scroll-behavior: auto !important; animation-duration: .01ms !important; transition-duration: .01ms !important; }
    }
</style>
