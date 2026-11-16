<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#263a1b">
    <title>@yield('title', 'Livestock For Sale') | AgriTimbang</title>
    <link rel="icon" href="{{ asset('assets/logo.png') }}">
    @vite(['resources/css/app-shell.css'])
    <style>
        body { background: #f5f5ee; color: #263a1b; }
        .public-header { background: #253a1e; color: #fff; }
        .public-header-inner, .public-container { width: min(1120px, calc(100% - 32px)); margin: 0 auto; }
        .public-header-inner { min-height: 72px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .public-brand { display: flex; align-items: center; gap: 10px; color: inherit; text-decoration: none; font-weight: 800; }
        .public-brand img { width: 40px; height: 40px; object-fit: contain; }
        .public-header a:not(.public-brand) { color: #fff; }
        .public-container { padding: 36px 0 56px; }
        .public-eyebrow { color: #60704f; font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .public-title { margin: 4px 0 8px; font: 800 clamp(1.7rem, 3vw, 2.4rem) Manrope, sans-serif; }
        .public-muted { color: #66705f; }
        .public-filter { display: flex; align-items: center; gap: 12px; margin: 24px 0; }
        .public-filter select, .form-control, .form-select { width: 100%; padding: 10px 12px; border: 1px solid #cbd3c4; border-radius: 8px; background: #fff; color: #263a1b; font: inherit; }
        .public-filter select { max-width: 360px; }
        .schedule-section { margin: 26px 0 36px; }
        .schedule-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
        .schedule-heading h2 { margin: 0 0 4px; font-size: 1.25rem; }
        .schedule-meta { color: #66705f; font-size: .93rem; }
        .livestock-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 16px; }
        .livestock-card { overflow: hidden; background: #fff; border: 1px solid #e2e7dc; border-radius: 12px; box-shadow: 0 4px 14px #263a1b0c; }
        .livestock-photo { width: 100%; height: 190px; object-fit: cover; background: #eef1e8; }
        .livestock-card-body { padding: 16px; }
        .livestock-card h3 { margin: 0; font-size: 1.08rem; }
        .livestock-card p { margin: 8px 0 0; color: #66705f; font-size: .9rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 1px solid transparent; border-radius: 8px; padding: 9px 14px; font: 600 .95rem "DM Sans", sans-serif; text-decoration: none; cursor: pointer; }
        .btn-primary { background: #355b2a; color: #fff; }
        .btn-primary:hover { background: #294820; color: #fff; }
        .btn-outline { background: #fff; color: #355b2a; border-color: #9bab91; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .alert { padding: 14px 16px; border-radius: 9px; background: #edf4e9; color: #355b2a; }
        .alert-warning { background: #fff7df; color: #775b13; }
        .alert-danger { background: #fbe9e7; color: #8d2a20; }
        .selected-bar { position: sticky; bottom: 0; z-index: 5; background: #fff; border-top: 1px solid #dfe5d9; box-shadow: 0 -4px 14px #263a1b12; padding: 12px 0; }
        .selected-bar-inner { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .selected-actions { display: flex; gap: 8px; }
        .livestock-checkbox { width: 20px; height: 20px; accent-color: #355b2a; }
        .interest-modal { position: fixed; inset: 0; z-index: 20; display: none; align-items: center; justify-content: center; padding: 18px; background: #1118; }
        .interest-modal.show { display: flex; }
        .modal-dialog { width: min(720px, 100%); max-height: 92vh; overflow: auto; }
        .modal-content { background: #fff; border-radius: 12px; box-shadow: 0 16px 50px #0003; }
        .modal-header, .modal-body { padding: 18px 20px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e9e1; }
        .modal-header h2 { margin: 0; font-size: 1.15rem; }
        .modal-close { border: 0; background: transparent; font-size: 1.5rem; color: #526047; cursor: pointer; }
        .form-label { display: block; margin: 0 0 6px; font-weight: 600; }
        .form-check { display: flex; align-items: flex-start; gap: 8px; margin: 12px 0; }
        .form-check-input { margin-top: 4px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .form-wide { grid-column: 1 / -1; }
        .form-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 99px; background: #edf1e9; color: #355b2a; font-size: .8rem; }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { border-bottom: 1px solid #e5e9e1; padding: 8px; text-align: left; }
        .list-group { list-style: none; padding: 0; margin: 8px 0; }
        .list-group-item { padding: 9px 10px; border: 1px solid #e5e9e1; }
        .livestock-details { list-style: none; padding: 0; margin: 14px 0 0; color: #66705f; font-size: .9rem; }
        .livestock-details li { margin: 6px 0; }
        .d-block { display: block; }
        .d-flex { display: flex; }
        .justify-content-between { justify-content: space-between; }
        .justify-content-end { justify-content: flex-end; }
        .justify-content-center { justify-content: center; }
        .align-items-center { align-items: center; }
        .align-items-start { align-items: flex-start; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-2 { gap: 8px; }
        .mb-0 { margin-bottom: 0; }
        .mb-1 { margin-bottom: 4px; }
        .mb-3 { margin-bottom: 14px; }
        .mt-1 { margin-top: 4px; }
        .mt-3 { margin-top: 14px; }
        .mt-4 { margin-top: 20px; }
        .py-3 { padding-top: 14px; padding-bottom: 14px; }
        .small { font-size: .85rem; }
        .text-muted { color: #66705f; }
        .text-success, .bg-success { color: #2f6d37; }
        .bg-danger { color: #a52c25; }
        .bg-secondary { color: #66705f; }
        .text-uppercase { text-transform: uppercase; }
        .text-center { text-align: center; }
        .fw-semibold { font-weight: 600; }
        .fw-bold { font-weight: 800; }
        .border { border: 1px solid #dfe5d9; }
        .alert-light { background: #f7f8f5; color: #263a1b; }
        .alert-success { background: #e8f4e7; color: #2f6d37; }
        .form-check-input { accent-color: #355b2a; }
        .form-check-label { line-height: 1.4; }
        .form-switch .form-check-input { width: 18px; height: 18px; }
        .row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .col-md-6 { min-width: 0; }
        .table-responsive { overflow-x: auto; }
        .table-sm th, .table-sm td { padding: 7px; }
        .btn-outline-secondary, .btn-outline-primary { background: #fff; color: #355b2a; border-color: #9bab91; }
        .btn-secondary { background: #66705f; color: #fff; }
        .modal-open { overflow: hidden; }
        @media (max-width: 600px) {
            .public-filter { align-items: stretch; flex-direction: column; }
            .form-grid { grid-template-columns: 1fr; }
            .row { grid-template-columns: 1fr; }
            .form-wide { grid-column: auto; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header class="public-header">
        <div class="public-header-inner">
            <a class="public-brand" href="{{ route('forsale') }}">
                <img src="{{ asset('assets/logo.png') }}" alt="">
                <span>AgriTimbang <small>Livestock for sale</small></span>
            </a>
            <a href="{{ route('login') }}">Sign in</a>
        </div>
    </header>
    <main>
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
