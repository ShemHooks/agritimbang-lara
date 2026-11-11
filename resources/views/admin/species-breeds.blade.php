@extends('layouts.app')

@section('content')
<div class="sb-page" id="species-breeds-page">
  <header class="sb-heading">
    <div><p class="sb-eyebrow">Administration / Reference Data</p><h1>Species & Breed Management</h1><p>Maintain livestock classifications and their availability across AgriTimbang.</p></div>
    <button type="button" class="sb-btn sb-btn-primary" id="sb-add">+ Add Species</button>
  </header>
  <div class="sb-tabs" role="tablist" aria-label="Reference data">
    <button type="button" class="sb-tab is-selected" id="sb-species-tab" role="tab" aria-selected="true" data-tab="species">Species</button>
    <button type="button" class="sb-tab" id="sb-breeds-tab" role="tab" aria-selected="false" data-tab="breeds">Breeds</button>
  </div>
  <section class="sb-panel">
    <div class="sb-toolbar">
      <label class="sb-search"><span class="sb-sr-only">Search</span><input id="sb-search" type="search" placeholder="Search by name or code" autocomplete="off"></label>
      <label class="sb-species-filter" id="sb-filter-wrap" hidden><span class="sb-sr-only">Filter by species</span><select id="sb-species-filter"><option value="">All species</option></select></label>
    </div>
    <div class="sb-alert" id="sb-alert" role="alert" hidden></div>
    <div class="sb-table-wrap"><table class="sb-table"><thead id="sb-table-head"></thead><tbody id="sb-table-body"></tbody></table></div>
    <div class="sb-footer"><span id="sb-count" aria-live="polite"></span><div class="sb-pager"><button type="button" class="sb-btn" id="sb-prev">Previous</button><span id="sb-page-label">Page 1</span><button type="button" class="sb-btn" id="sb-next">Next</button></div></div>
  </section>
</div>

<div class="sb-overlay" id="sb-modal" hidden>
  <div class="sb-dialog" role="dialog" aria-modal="true" aria-labelledby="sb-modal-title">
    <div class="sb-dialog-head"><h2 id="sb-modal-title">Add Species</h2><button type="button" class="sb-icon-button" id="sb-modal-close" aria-label="Close">×</button></div>
    <form id="sb-form" novalidate>
      <div class="sb-form-errors" id="sb-form-errors" role="alert" hidden></div>
      <div class="sb-field" id="sb-parent-field" hidden><label for="sb-parent">Species <span>*</span></label><select id="sb-parent" name="species_id"></select><small class="sb-error" data-error="species_id"></small></div>
      <div class="sb-field"><label for="sb-name">Name <span>*</span></label><input id="sb-name" name="name" maxlength="100" required><small class="sb-error" data-error="name"></small></div>
      <div class="sb-field"><label for="sb-code">Code <span id="sb-code-required">*</span></label><input id="sb-code" name="code" maxlength="50" autocapitalize="characters"><small class="sb-error" data-error="code"></small></div>
      <div class="sb-field"><label for="sb-description">Description</label><textarea id="sb-description" name="description" rows="3" maxlength="1000"></textarea><small class="sb-error" data-error="description"></small></div>
      <div class="sb-dialog-actions"><button type="button" class="sb-btn" id="sb-cancel">Cancel</button><button type="submit" class="sb-btn sb-btn-primary" id="sb-save">Save</button></div>
    </form>
  </div>
</div>

<div class="sb-overlay" id="sb-confirm" hidden>
  <div class="sb-dialog sb-dialog-small" role="alertdialog" aria-modal="true" aria-labelledby="sb-confirm-title" aria-describedby="sb-confirm-message">
    <h2 id="sb-confirm-title">Change status?</h2><p id="sb-confirm-message"></p>
    <div class="sb-dialog-actions"><button type="button" class="sb-btn" id="sb-confirm-cancel">Cancel</button><button type="button" class="sb-btn sb-btn-primary" id="sb-confirm-yes">Confirm</button></div>
  </div>
</div>
@vite(['resources/css/species-breeds.css', 'resources/js/pages/species-breeds.js'])
@endsection
