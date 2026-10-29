<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#263a1b">
  <title>@yield('title', 'Dashboard') | AgriTimbang</title>
  <link rel="icon" href="{{ asset('assets/logo.png') }}">
  @vite(['resources/css/app-shell.css', 'resources/js/layouts/app-shell.js'])
  @stack('styles')
</head>

<body>
  <div class="app-loading" id="app-loading" role="status">Loading your workspace…</div>
  <div class="app-shell" id="app-shell" hidden>
    <aside class="sidebar" id="app-sidebar" aria-label="Main navigation">
      <a class="brand" href="/dashboard"><img src="{{ asset('assets/logo.png') }}"
          alt=""><span><strong>AgriTimbang</strong><small>Livestock management</small></span></a>
      <div class="nav-caption">WORKSPACE</div>
      <nav id="app-nav" aria-label="Workspace"></nav>
      <div class="sidebar-bottom">
        <div class="identity"><span class="avatar" id="sidebar-avatar">A</span><span><strong
              id="sidebar-name">Account</strong><small id="sidebar-role">User</small></span></div><button type="button"
          class="sidebar-logout" data-logout>Sign out <span>→</span></button>
      </div>
    </aside>
    <button type="button" class="sidebar-backdrop" id="sidebar-backdrop" aria-label="Close navigation" hidden></button>
    <div class="main-shell">
      <header class="topbar">
        <div class="topbar-start"><button type="button" id="menu-toggle" class="icon-button menu-toggle"
            aria-label="Open navigation" aria-expanded="false">
            <i data-lucide="menu"></i>
          </button>
          <div class="breadcrumbs"><span>Workspace</span><span class="crumb-divider">/</span><strong
              id="current-page">Overview</strong></div>
        </div>
        <div class="topbar-actions">

          <div class="notification-wrap">
            <button type="button" class="icon-button notification-trigger" id="notification-button"
              aria-label="Notifications" aria-expanded="false" aria-controls="notification-panel" title="Notifications">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                aria-hidden="true">
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                <path d="M10 21h4" />
              </svg>
              <span id="notification-count" class="notification-count" hidden>0</span>
            </button>

            <section id="notification-panel" class="notification-panel" aria-label="Notifications" hidden>
              <div class="notification-panel-header">
                <h2>Notifications</h2>
                <button type="button" id="notification-read-all" class="notification-read-all">
                  Mark all as read
                </button>
              </div>

              <div id="notification-list" class="notification-list"></div>
              <p id="notification-status" class="notification-status" role="status" hidden></p>

              <button type="button" id="notification-load-more" class="notification-load-more" hidden>
                Load more notifications
              </button>
            </section>
          </div>


          <div class="user-menu-wrap"><button type="button" class="user-menu-button" id="user-menu-button"
              aria-expanded="false"><span class="avatar small" id="header-avatar">A</span><span
                id="header-name">Account</span><span>⌄</span></button>
            <div class="user-dropdown" id="user-dropdown" hidden>
              <div class="dropdown-identity"><strong id="dropdown-name">Account</strong><small
                  id="dropdown-email"></small></div><button type="button" data-logout>Sign out</button>
            </div>
          </div>
        </div>
      </header>
      <main class="page-content" id="page-content">@yield('content')</main>
    </div>
  </div>
  <div id="app-error" class="app-error" role="alert" hidden></div>
  @stack('script')
</body>

</html>