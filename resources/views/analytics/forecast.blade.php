@extends('layouts.app')

@section('title', 'Price Forecast | AgriTimbang')

@section('content')
    <div class="forecast-page" id="forecast-page">
        <div class="forecast-heading">
            <div>
                <p class="forecast-eyebrow">ANALYTICS / PRICE FORECAST</p>
                <h1>Livestock Price Forecast</h1>
                <p>Explore official weekly prices and ARIMA-based projections.</p>
            </div>
            <span class="forecast-model-tag">ARIMA (1,1,0)</span>
        </div>

        <form id="forecast-form" class="forecast-panel forecast-filters">
            <div class="forecast-field"><label for="forecast-municipality">Municipality</label><select
                    id="forecast-municipality">
                    <option value="">Use my assigned municipality</option>
                </select></div>
            <div class="forecast-field"><label for="forecast-species">Species <span
                        aria-hidden="true">*</span></label><select id="forecast-species" required>
                    <option value="">Select species</option>
                </select></div>
            <div class="forecast-field"><label for="forecast-purpose">Sale purpose</label><select id="forecast-purpose">
                    <option value="">Unspecified (NULL)</option>
                    <option value="slaughter">Slaughter</option>
                    <option value="breeding">Breeding</option>
                    <option value="fattening">Fattening</option>
                    <option value="work">Work</option>
                </select></div>
            <div class="forecast-field"><label for="forecast-weeks">Forecast horizon</label><select id="forecast-weeks">
                    <option value="4">4 weeks</option>
                    <option value="1">1 week</option>
                    <option value="2">2 weeks</option>
                    <option value="6">6 weeks</option>
                    <option value="8">8 weeks</option>
                    <option value="12">12 weeks</option>
                </select></div>
            <div class="forecast-field forecast-submit"><button type="submit" id="forecast-run">Generate forecast</button>
            </div>
        </form>

        <div id="forecast-message" class="forecast-message" role="status" aria-live="polite">Select a species and generate a
            forecast.</div>
        <div id="forecast-results" hidden>
            <div class="forecast-stats">
                <article class="forecast-panel forecast-stat"><span>Latest official weekly average</span><strong
                        id="forecast-latest">—</strong><small id="forecast-latest-date">—</small></article>
                <article class="forecast-panel forecast-stat"><span>Final projected price</span><strong
                        id="forecast-final">—</strong><small id="forecast-final-date">—</small></article>
                <article class="forecast-panel forecast-stat"><span>Projected change</span><strong
                        id="forecast-change">—</strong><small>Versus latest observed week</small></article>
                <article class="forecast-panel forecast-stat"><span>Observed history</span><strong
                        id="forecast-count">—</strong><small>Consecutive weekly observations</small></article>
            </div>
            <section class="forecast-panel forecast-chart-panel">
                <div class="forecast-section-heading">
                    <div>
                        <h2>Price trend & projection</h2>
                        <p>Historical weekly average and forecast, in PHP per kilogram</p>
                    </div>
                    <div class="forecast-legend"><span><i class="forecast-dot historical"></i>Historical</span><span><i
                                class="forecast-dot predicted"></i>Forecast</span><span><i
                                class="forecast-dot interval"></i>95% interval</span></div>
                </div>
                <div class="forecast-chart-wrap"><canvas id="forecast-chart" role="img"
                        aria-label="Historical and projected livestock prices"></canvas></div>
            </section>
            <section class="forecast-panel forecast-table-panel">
                <div class="forecast-section-heading">
                    <div>
                        <h2>Weekly forecast details</h2>
                        <p>Projected price and 95% confidence interval</p>
                    </div>
                </div>
                <div class="forecast-table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Week starting</th>
                                <th>Projected (PHP/kg)</th>
                                <th>Lower 95%</th>
                                <th>Upper 95%</th>
                            </tr>
                        </thead>
                        <tbody id="forecast-table-body"></tbody>
                    </table>
                </div>
            </section>
            <p class="forecast-disclaimer" id="forecast-disclaimer"></p>
        </div>
    </div>
@endsection

@push('styles')
    @vite('resources/css/forecast.css')
@endpush

@vite('resources/js/pages/forecast.js')