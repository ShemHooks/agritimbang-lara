@extends('layouts.app')

@section('title', 'Audit Logs')

@push('styles')
    @vite('resources/css/audit-logs.css')
@endpush

@section('content')
    <div class="audit-container">

        {{-- Page Header --}}
        <header class="audit-heading">
            <div>
                <p class="audit-eyebrow">SYSTEM ADMINISTRATION</p>
                <h1>Audit Logs</h1>
                <p>
                    Monitor system activities, track administrative
                    actions, and review historical records.
                </p>
            </div>

            <button id="audit-refresh" type="button">
                Refresh
            </button>
        </header>

        {{-- Global Alert --}}
        <div id="audit-alert" class="audit-alert" role="alert" hidden></div>

        {{-- Audit Log Listing --}}
        <section class="audit-card">

            <div class="audit-toolbar">
                <div>
                    <h2>Activity History</h2>
                    <p>
                        Review recorded actions across your
                        authorized system scope.
                    </p>
                </div>
            </div>

            {{-- Filters --}}
            <form id="audit-filter-form" class="audit-filters">

                <input id="audit-user" type="text" placeholder="User UUID" aria-label="Filter by user ID">

                <input id="audit-action" type="text" placeholder="Exact action" aria-label="Filter by action">

                <input id="audit-from" type="date" aria-label="Start date">

                <input id="audit-to" type="date" aria-label="End date">

                <input id="audit-entity-type" type="text" placeholder="Entity type" aria-label="Filter by entity type">

                <input id="audit-entity-id" type="text" placeholder="Entity UUID" aria-label="Filter by entity ID">

                <select id="audit-per-page" aria-label="Records per page">
                    <option value="20">20 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>

                <button type="submit" class="audit-primary">
                    Apply Filters
                </button>

                <button id="audit-reset" type="button">
                    Reset
                </button>

            </form>

            {{-- Audit Log Table --}}
            <div class="audit-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Performed By</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Record ID</th>
                            <th>Municipality ID</th>
                        </tr>
                    </thead>

                    <tbody id="audit-rows">
                        <tr>
                            <td colspan="6">
                                Loading audit logs...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <footer class="audit-pagination">

                <button id="audit-previous" type="button">
                    Previous
                </button>

                <span id="audit-page-label">
                    Page 1
                </span>

                <button id="audit-next" type="button">
                    Next
                </button>

            </footer>

        </section>
    </div>
@endsection

@push('script')
    @vite('resources/js/pages/audit-logs.js')
@endpush