<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>AgriTimbang Dashboard</title>

    @vite(['resources/js/pages/dashboard.js'])
</head>

<body style="font-family:sans-serif;padding:3rem">

    <main id="dashboard-content" hidden>
        <h1>Welcome to AgriTimbang</h1>

        <p>
            Signed in as <strong id="user-name"></strong>.
            Dashboard UI is next.
        </p>

        <button id="logout-button" type="button">
            Sign out
        </button>
    </main>

    <p id="loading-message">Verifying your session...</p>

</body>

</html>