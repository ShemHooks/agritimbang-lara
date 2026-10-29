<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">

    <title>Buyer Confirmation | AgriTimbang</title>

    @vite([
        'resources/css/buyer-confirmation.css',
        'resources/js/pages/buyer-confirmation.js'
    ])
</head>

<body>
    <main class="confirmation-container" id="buyer-confirmation" data-token="{{ $token }}">

        <header class="brand">
            <div class="brand-mark">AT</div>

            <div>
                <h1>AgriTimbang</h1>
                <p>Secure Buyer Confirmation</p>
            </div>
        </header>

        <section class="confirmation-card">
            <div id="loading-state">
                <h2>Loading transaction...</h2>
                <p>Please wait while we verify your link.</p>
            </div>

            <div id="error-state" hidden>
                <h2>Unable to open confirmation</h2>
                <p id="error-message"></p>
            </div>

            <div id="transaction-state" hidden>
                <span class="status-badge">
                    Awaiting your confirmation
                </span>

                <h2>Review Your Purchase</h2>

                <p class="description">
                    Please verify the transaction details
                    before confirming your purchase.
                </p>

                <div class="details">
                    <div class="detail-row">
                        <span>Buyer</span>
                        <strong id="buyer-name">—</strong>
                    </div>

                    <div class="detail-row">
                        <span>Seller</span>
                        <strong id="seller-name">—</strong>
                    </div>

                    <div class="detail-row">
                        <span>Transaction Date</span>
                        <strong id="transaction-date">—</strong>
                    </div>

                    <div class="detail-row">
                        <span>Total Selling Price</span>
                        <strong id="selling-price">—</strong>
                    </div>

                    <div class="detail-row">
                        <span>Link Expires</span>
                        <strong id="expires-at">—</strong>
                    </div>
                </div>

                <h3>Livestock Items</h3>
                <div id="livestock-items"></div>

                <p class="notice">
                    Confirm only if you recognize this
                    transaction and all details are correct.
                </p>

                <p id="action-error" class="action-error" role="alert" hidden></p>

                <button id="confirm-btn" class="btn btn-primary" type="button">
                    Confirm Transaction
                </button>

                <button id="dispute-toggle" class="btn btn-outline" type="button">
                    Report an Issue
                </button>

                <form id="dispute-form" hidden>
                    <label for="dispute-reason">
                        Describe the issue
                    </label>

                    <textarea id="dispute-reason" maxlength="2000" required
                        placeholder="Explain why this transaction is incorrect..."></textarea>

                    <button class="btn btn-danger" type="submit">
                        Submit Dispute
                    </button>
                </form>
            </div>

            <div id="success-state" hidden>
                <h2 id="success-title"></h2>
                <p id="success-message"></p>
            </div>
        </section>

        <footer>
            AgriTimbang — Livestock Transaction Verification
        </footer>
    </main>
</body>

</html>