@extends('layouts.app')

@section('content')
    <main class="profile-page" id="profile-settings">
        <header class="profile-heading">
            <p class="profile-eyebrow">AGRITIMBANG / MY ACCOUNT</p>
            <h1>Profile Settings</h1>
            <p>Manage your personal information and account security.</p>
        </header>

        <div id="profile-alert" class="profile-alert" role="status" hidden></div>

        <section class="profile-card profile-identity">
            <div class="profile-avatar" id="profile-avatar">?</div>
            <div class="profile-identity-info">
                <h2 id="profile-display-name">Loading profile...</h2>
                <p id="profile-display-email">—</p>
                <div class="profile-badges">
                    <span id="profile-role" class="profile-badge">—</span>
                    <span id="profile-account-status" class="profile-badge">—</span>
                </div>
            </div>
        </section>

        <div class="profile-layout">
            <nav class="profile-menu" aria-label="Profile settings">
                <button type="button" class="active" data-profile-tab="personal">
                    Personal Information
                </button>
                <button type="button" data-profile-tab="security">
                    Password & Security
                </button>
                <button type="button" data-profile-tab="account">
                    Account Information
                </button>
            </nav>

            <div class="profile-content">
                <section class="profile-card" data-profile-panel="personal">
                    <div class="profile-section-heading">
                        <h2>Personal Information</h2>
                        <p>Update the contact details associated with your account.</p>
                    </div>

                    <form id="profile-personal-form">
                        <div class="profile-fields">
                            <label>
                                First Name
                                <input name="first_name" required maxlength="100">
                            </label>

                            <label>
                                Middle Name
                                <input name="middle_name" maxlength="100">
                            </label>

                            <label>
                                Last Name
                                <input name="last_name" required maxlength="100">
                            </label>

                            <label>
                                Phone Number
                                <input name="phone_number" type="tel" maxlength="30">
                            </label>

                            <label class="profile-full">
                                Email Address
                                <input name="email" type="email" required maxlength="255">
                            </label>
                        </div>

                        <div class="profile-actions">
                            <button type="submit" class="profile-primary" id="profile-save">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </section>

                <section class="profile-card" data-profile-panel="security" hidden>
                    <div class="profile-section-heading">
                        <h2>Password & Security</h2>
                        <p>Protect your account by using a strong password.</p>
                    </div>

                    <form id="profile-password-form">
                        <div class="profile-fields">
                            <label class="profile-full">
                                Current Password
                                <input name="current_password" type="password" autocomplete="current-password" required>
                            </label>

                            <label>
                                New Password
                                <input name="password" type="password" autocomplete="new-password" minlength="8" required>
                            </label>

                            <label>
                                Confirm New Password
                                <input name="password_confirmation" type="password" autocomplete="new-password"
                                    minlength="8" required>
                            </label>
                        </div>

                        <p class="profile-hint">
                            Use at least 8 characters, including letters and numbers.
                        </p>

                        <div class="profile-actions">
                            <button type="submit" class="profile-primary" id="profile-change-password">
                                Update Password
                            </button>
                        </div>
                    </form>
                </section>

                <section class="profile-card" data-profile-panel="account" hidden>
                    <div class="profile-section-heading">
                        <h2>Account Information</h2>
                        <p>View your account status and assigned permissions.</p>
                    </div>

                    <div id="profile-account-details" class="profile-details">
                        Loading account details...
                    </div>

                    <p class="profile-hint">
                        Roles, municipality assignments, and verification
                        statuses are managed by authorized personnel.
                    </p>
                </section>
            </div>
        </div>
    </main>
@endsection

@vite([
    'resources/css/profile.css',
    'resources/js/pages/profile.js'
])