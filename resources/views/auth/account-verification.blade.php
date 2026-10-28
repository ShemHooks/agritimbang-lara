<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification | AgriTimbang</title>
    <link rel="icon" href="{{ asset('assets/logo.png') }}">

    @vite([
        'resources/css/agritimbang-auth.css',
        'resources/css/account-verification.css',
        'resources/js/pages/account-verification.js'
    ])
</head>

<body class="verification-body">
    <main class="verification-layout" id="verification-app">

        <header class="verification-header">
            <div class="verification-header-inner">
                <div class="verification-brand">
                    <img src="{{ asset('assets/logo.png') }}" alt="AgriTimbang logo">
                    <div class="brand-copy">
                        <strong>AgriTimbang</strong>
                        <span>Farmer verification portal</span>
                    </div>
                </div>

                <button id="logout-button" type="button" class="verification-logout">
                    <span>Sign out</span>
                    <span aria-hidden="true">↗</span>
                </button>
            </div>
        </header>

        <div class="verification-content">

            <div class="verification-intro">
                <span class="verification-eyebrow">
                    SECURE ACCOUNT ONBOARDING
                </span>
                <h1>Let's get you verified.</h1>
                <p>
                    Just a few steps to complete your farmer account
                    and connect with your local agriculture office.
                </p>
            </div>

            <div class="verification-steps" aria-label="Verification progress">

                <div class="verification-step completed" id="step-email">
                    <span class="step-number">✓</span>
                    <div class="step-copy">
                        <strong>Email</strong>
                        <span>Verified</span>
                    </div>
                </div>

                <span class="step-connector" aria-hidden="true"></span>

                <div class="verification-step" id="step-profile">
                    <span class="step-number">2</span>
                    <div class="step-copy">
                        <strong>Profile</strong>
                        <span>Farm location</span>
                    </div>
                </div>

                <span class="step-connector" aria-hidden="true"></span>

                <div class="verification-step" id="step-document">
                    <span class="step-number">3</span>
                    <div class="step-copy">
                        <strong>Identity</strong>
                        <span>LGU review</span>
                    </div>
                </div>
            </div>

            <div id="verification-loading" class="verification-card loading-card" role="status">
                <div class="loading-spinner"></div>
                <h2>Checking your account</h2>
                <p>We're loading your verification progress.</p>
            </div>

            <div id="verification-error" class="verification-alert" role="alert" hidden></div>

            <!-- FARMER PROFILE -->
            <section id="profile-section" class="verification-card" hidden>

                <div class="card-topline">
                    <span class="card-step-label">STEP 02 / 03</span>
                    <span class="card-step-context">Farmer information</span>
                </div>

                <div class="card-heading">
                    <div class="section-icon">⌖</div>
                    <div>
                        <h2>Where is your farm located?</h2>
                        <p class="section-description">
                            Provide your location so the correct LGU
                            office can review your application.
                        </p>
                    </div>
                </div>

                <form id="profile-form">
                    <div class="form-grid">
                        <label class="verification-field">
                            <span>Municipality <em>*</em></span>
                            <select name="municipality_id" id="municipality-select" required>
                                <option value="">
                                    Select municipality
                                </option>
                            </select>
                        </label>

                        <label class="verification-field">
                            <span>Barangay <em>*</em></span>
                            <select name="barangay_id" id="barangay-select" required disabled>
                                <option value="">
                                    Select barangay
                                </option>
                            </select>
                        </label>
                    </div>

                    <label class="verification-field">
                        <span>Complete address
                            <small>Optional</small>
                        </span>
                        <textarea name="address" rows="3" maxlength="500"
                            placeholder="House number, street, purok, or nearby landmark"></textarea>
                        <small class="field-hint">
                            Help your local office identify your location.
                        </small>
                    </label>

                    <div class="verification-info">
                        <span class="info-icon">ⓘ</span>
                        <p>
                            Your municipality determines which
                            agriculture office will handle your
                            identity verification.
                        </p>
                    </div>

                    <button type="submit" class="verification-primary">
                        <span>Save and continue</span>
                        <span aria-hidden="true">→</span>
                    </button>
                </form>
            </section>

            <!-- DOCUMENT UPLOAD -->
            <section id="document-section" class="verification-card" hidden>

                <div class="card-topline">
                    <span class="card-step-label">STEP 03 / 03</span>
                    <span class="card-step-context">Identity verification</span>
                </div>

                <div class="card-heading">
                    <div class="section-icon">▣</div>
                    <div>
                        <h2>Upload your valid ID</h2>
                        <p class="section-description">
                            Submit one clear identification document
                            for review by your LGU authority.
                        </p>
                    </div>
                </div>

                <form id="document-form">
                    <label class="verification-field">
                        <span>Identification type <em>*</em></span>
                        <select name="document_type" required>
                            <option value="">Choose ID type</option>
                            <option value="national_id">National ID</option>
                            <option value="drivers_license">Driver's License</option>
                            <option value="passport">Passport</option>
                            <option value="voters_id">Voter's ID</option>
                            <option value="barangay_id">Barangay ID</option>
                            <option value="other">Other</option>
                        </select>
                    </label>

                    <label class="verification-field">
                        <span>
                            ID number
                            <small>Optional</small>
                        </span>
                        <input type="text" name="document_number" maxlength="100" placeholder="Enter your ID number">
                    </label>

                    <div class="verification-field">
                        <span>Identification document <em>*</em></span>

                        <label class="verification-upload" id="verification-dropzone" for="document-file">
                            <span class="upload-illustration" aria-hidden="true">↑</span>
                            <strong>
                                Click to select your document
                            </strong>
                            <span class="upload-description">
                                Or drag and drop your file here
                            </span>
                            <span class="upload-format">
                                JPG, PNG or PDF · Up to 5 MB
                            </span>

                            <span id="selected-file-name" class="selected-file">
                                No file selected
                            </span>

                            <input type="file" id="document-file" name="document" accept=".jpg,.jpeg,.png,.pdf"
                                required>
                        </label>
                    </div>

                    <div class="verification-info">
                        <span class="info-icon">♢</span>
                        <p>
                            Your ID is stored privately and can only
                            be accessed by authorized reviewers.
                            Make sure all details are readable.
                        </p>
                    </div>

                    <button type="submit" class="verification-primary">
                        <span>Submit for verification</span>
                        <span aria-hidden="true">→</span>
                    </button>
                </form>
            </section>

            <!-- PENDING -->
            <section id="pending-section" class="verification-card verification-status" hidden>

                <div class="status-icon pending-icon">◷</div>

                <span class="status-pill pending-pill">
                    UNDER REVIEW
                </span>

                <h2>Your application is on its way!</h2>

                <p class="status-description">
                    Your identification document has been submitted.
                    Your municipal agriculture office will review
                    your information before approving your account.
                </p>

                <div class="review-timeline">
                    <div class="timeline-item done">
                        <span class="timeline-dot">✓</span>
                        <div>
                            <strong>Document submitted</strong>
                            <span>Your ID was received successfully.</span>
                        </div>
                    </div>

                    <div class="timeline-item current">
                        <span class="timeline-dot">2</span>
                        <div>
                            <strong>System verification</strong>
                            <span>Waiting for an authorized reviewer.</span>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <span class="timeline-dot">3</span>
                        <div>
                            <strong>Account approval</strong>
                            <span>You'll receive your verification result.</span>
                        </div>
                    </div>
                </div>

                <div class="status-details">
                    <div>
                        <span>Status</span>
                        <strong>Pending review</strong>
                    </div>
                    <div>
                        <span>Document type</span>
                        <strong id="pending-document-type">—</strong>
                    </div>
                    <div>
                        <span>Submitted on</span>
                        <strong id="pending-submitted-at">—</strong>
                    </div>
                </div>

                <button type="button" id="refresh-status" class="verification-secondary">
                    Refresh verification status
                </button>

                <p class="status-footnote">
                    You don't need to upload another document
                    while your application is under review.
                </p>
            </section>

            <!-- REJECTED -->
            <section id="rejected-section" class="verification-card verification-status" hidden>

                <div class="status-icon rejected-icon">!</div>

                <span class="status-pill rejected-pill">
                    ACTION REQUIRED
                </span>

                <h2>Let's try that again.</h2>

                <p class="status-description">
                    Your verification document wasn't approved.
                    You can review the feedback and submit
                    another document.
                </p>

                <div class="rejection-reason">
                    <strong>Feedback from your LGU reviewer</strong>
                    <p id="rejection-message">—</p>
                </div>

                <button type="button" id="resubmit-button" class="verification-primary">
                    <span>Submit another document</span>
                    <span aria-hidden="true">→</span>
                </button>
            </section>

            <!-- APPROVED -->
            <section id="approved-section" class="verification-card verification-status" hidden>

                <div class="status-icon approved-icon">✓</div>

                <span class="status-pill approved-pill">
                    ACCOUNT VERIFIED
                </span>

                <h2>You're officially verified!</h2>

                <p class="status-description">
                    System Admin has approved your farmer account.
                    You're ready to continue using AgriTimbang.
                </p>

                <a href="/dashboard" class="verification-primary verification-link">
                    <span>Continue to dashboard</span>
                    <span aria-hidden="true">→</span>
                </a>
            </section>

            <footer class="verification-footer">
                <span>© {{ date('Y') }} AgriTimbang</span>
                <span>Secure farmer verification</span>
            </footer>
        </div>
    </main>
</body>

</html>