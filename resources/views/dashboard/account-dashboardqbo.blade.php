@extends('layouts.admin')

@section('page-title')
    {{ __('Dashboard') }}
@endsection

@push('script-page')
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css" />
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>

    <style>
        /* minimal styles for layout and hiding scrollbars inside swiper containers */
        .center-welcome {
            text-align: center;
            margin: 20px 0;
        }

        .link-btn {
            border-radius: 30px;
            padding: .5rem 1rem;
            border: 1px solid;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .link-icon {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .small-arrow {
            font-size: 14px;
            cursor: pointer;
        }

        .card-rect {
            min-width: 280px;
            max-width: 380px;
            height: 160px;
        }

        .card-rect .card-body {
            overflow: hidden;
        }

        .panel-rounded {
            border: 1px solid rgba(0, 0, 0, .125);
            border-radius: .75rem;
            padding: 1rem;
        }

        .stat-small {
            font-size: .9rem;
        }

        .inline-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: .75rem;
        }

        /* ensure swiper slides don't show scrollbar */
        .swiper {
            padding-bottom: 12px;
        }

        .swiper-button-custom {
            display: flex;
            gap: .5rem;
            align-items: center;
        }

        /* small pagination text on top right of card area */
        .cards-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: .5rem;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Links swiper (horizontal buttons)
            const linksSwiper = new Swiper(".links-swiper", {
                slidesPerView: 'auto',
                spaceBetween: 12,
                freeMode: true,
                mousewheel: false,
                scrollbar: false,
            });

            // Business feed cards swiper
            const cardsSwiper = new Swiper(".cards-swiper", {
                slidesPerView: 'auto',
                spaceBetween: 18,
                navigation: {
                    nextEl: ".cards-next",
                    prevEl: ".cards-prev",
                },
                pagination: {
                    el: '.cards-pagination-text',
                    type: 'custom',
                    renderCustom: function (swiper, current, total) {
                        return current + ' of ' + total;
                    }
                },
            });

            // Sales funnel / P&L / Expenses charts
            // Profit & Loss - horizontal bars (income vs expenses)
            // const plCtx = document.getElementById('plChart');
            // const plChart = new Chart(plCtx, {
            //     type: 'bar',
            //     data: {
            //         labels: ['Income', 'Expenses'],
            //         datasets: [{
            //             label: 'Amount',
            //             data: [4200, 4900],
            //             backgroundColor: [
            //                 getComputedStyle(document.documentElement).getPropertyValue('--bs-success').trim(),
            //                 getComputedStyle(document.documentElement).getPropertyValue('--bs-danger').trim()
            //             ]
            //         }]
            //     },
            //     options: {
            //         indexAxis: 'y',
            //         responsive: true,
            //         plugins: {
            //             legend: { display: false }
            //         },
            //         scales: {
            //             x: { beginAtZero: true }
            //         }
            //     }
            // });


            // Expenses pie chart
            // const expCtx = document.getElementById('expChart');
            // const expChart = new Chart(expCtx, {
            //     type: 'pie',
            //     data: {
            //         labels: ['Marketing', 'Salaries', 'Tools', 'Rent'],
            //         datasets: [{
            //             data: [1200, 2300, 500, 900],
            //             // colors: let Chart.js pick or rely on defaults. We will map to bootstrap classes using CSS is avoided; Chart will use defaults.
            //         }]
            //     },
            //     options: {
            //         responsive: true,
            //     }
            // });

            // Hook filters dropdowns (simulate data change)
            document.querySelectorAll('.date-filter').forEach(function (el) {
                el.addEventListener('change', function () {
                    // For demo, randomize charts slightly to show interactivity
                    const r = () => Math.floor(Math.random() * 5000);
                    plChart.data.datasets[0].data = [r(), r()];
                    plChart.update();

                    expChart.data.datasets[0].data = [Math.floor(Math.random() * 1500), Math.floor(Math.random() * 2500), Math.floor(Math.random() * 900), Math.floor(Math.random() * 1200)];
                    expChart.update();
                });
            });

            // "Request payment" button (example)
            document.getElementById('requestPaymentBtn')?.addEventListener('click', function () {
                // For now, show a simple bootstrap modal or alert - we will use alert for minimal dependency
                alert('Request payment clicked — wire to your create payment flow.');
            });
        });
    </script>
@endpush

@section('content')
    @php
        // Links array (rounded outline buttons). Edit these entries as needed.
        $topLinks = [
            ['icon' => 'fa-solid fa-file-invoice', 'text' => 'Invoices', 'link' => '#invoices'],
            ['icon' => 'fa-solid fa-credit-card', 'text' => 'Payments', 'link' => '#payments'],
            ['icon' => 'fa-solid fa-box', 'text' => 'Products', 'link' => '#products'],
            ['icon' => 'fa-solid fa-users', 'text' => 'Customers', 'link' => '#customers'],
            ['icon' => 'fa-solid fa-chart-line', 'text' => 'Reports', 'link' => '#reports'],
            ['icon' => 'fa-solid fa-gear', 'text' => 'Settings', 'link' => '#settings'],
            // add more...
        ];

        // Business feed cards array
        $feedCards = [
            [
                'icon' => 'fa-solid fa-bell',
                'heading' => 'New Invoice Created',
                'description' => 'Invoice #INV-1023 was created by Sales team.',
                'link_text' => 'View Invoice',
                'link' => '#'
            ],
            [
                'icon' => 'fa-solid fa-user-plus',
                'heading' => 'New Customer',
                'description' => 'Acme Corp added as a new customer.',
                'link_text' => 'View Customer',
                'link' => '#'
            ],
            [
                'icon' => 'fa-solid fa-exclamation-triangle',
                'heading' => 'Overdue Payment',
                'description' => 'Invoice #INV-998 is overdue by 10 days.',
                'link_text' => 'Collect Payment',
                'link' => '#'
            ],
            [
                'icon' => 'fa-solid fa-calendar-check',
                'heading' => 'Subscription Expiring',
                'description' => 'A subscription for Beta LLC will expire soon.',
                'link_text' => 'Renew',
                'link' => '#'
            ],
            // add more...
        ];
    @endphp

    <!-- Welcome center -->
    <div class="center-welcome">
        <h3 class="mb-1">Welcome back — here's what's happening</h3>
        <p class="text-muted small">Overview of your business and recent activity</p>
    </div>

    <!-- Links slider -->
    <div class="mb-4">
        <div class="swiper links-swiper">
            <div class="swiper-wrapper">
                @foreach($topLinks as $l)
                    <div class="swiper-slide" style="width:auto;">
                        <a href="{{ $l['link'] }}" class="link-btn btn btn-outline-secondary no-underline" role="button">
                            <span class="link-icon text-secondary"><i class="{{ $l['icon'] }}"></i></span>
                            <span>{{ $l['text'] }}</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Business Feed -->
    <div class="mb-4">
        <div class="inline-heading">
            <h5 class="mb-0">Business Feed</h5>
            <div class="cards-topbar">
                <!-- arrows -->
                <div class="swiper-button-custom me-3">
                    <button class="btn btn-outline-secondary btn-sm cards-prev"><i class="fa fa-chevron-left"></i></button>
                    <button class="btn btn-outline-secondary btn-sm cards-next"><i class="fa fa-chevron-right"></i></button>
                </div>
                <div class="cards-pagination-text small text-muted"></div>
            </div>
        </div>

        <div class="swiper cards-swiper">
            <div class="swiper-wrapper">
                @foreach($feedCards as $card)
                    <div class="swiper-slide card-rect">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="fs-4 text-primary"><i class="{{ $card['icon'] }}"></i></div>
                                        <h6 class="mb-0">{{ $card['heading'] }}</h6>
                                    </div>
                                </div>
                                <p class="text-muted small flex-grow-1">{{ $card['description'] }}</p>
                                <div class="mt-2">
                                    <a href="{{ $card['link'] }}"
                                        class="btn btn-outline-primary btn-sm">{{ $card['link_text'] }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Business at a glance -->
    <div class="row g-4 align-items-stretch mb-4">
        <div class="d-flex justify-content-between align-items-center ">
            <h5 class="mb-0">Business at a glance</h5>
            <!-- empty or extra controls -->
        </div>


        <div class="col-lg-8 d-flex">
            <div class="panel-rounded flex-fill">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h6 class="mb-0">SALES & GET PAID FUNNEL</h6>
                    <select id="dateFilter" class="form-select w-auto">
                        <option value="last_30_days">Last 30 days</option>
                        <option value="last_month">Last Month</option>
                        <option value="last_quarter">Last Quarter</option>
                        <option value="last_year">Last Year</option>
                    </select>
                </div>

                <div class="row g-3 align-items-stretch">
                    <div class="col-md-3 d-flex">
                        <div class="card text-center shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title">Create Payment</h6>
                                <p class="card-text stat-small text-muted">Quickly make a new payment</p>
                                <button id="requestPaymentBtn" class="btn btn-primary btn-sm">Request Payment</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 d-flex">
                        <div class="card shadow-sm w-100 border-top border-5 border-info">
                            <div class="card-body text-center">
                                <h6 class="mb-3">Not Paid</h6>
                                <div id="notPaid" class="h3 mb-0">$0</div>
                                <p class="small text-muted">Unpaid invoices</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 d-flex">
                        <div class="card shadow-sm w-100 border-top border-5 border-info">
                            <div class="card-body text-center">
                                <h6 class="mb-3">Paid</h6>
                                <div id="paid" class="h3 mb-0">$0</div>
                                <p class="small text-muted">Amount received</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 d-flex">
                        <div class="card shadow-sm w-100 border-top border-5 border-info">
                            <div class="card-body text-center">
                                <h6 class="mb-3">Deposited</h6>
                                <div id="deposited" class="h3 mb-0">$0</div>
                                <p class="small text-muted">Bank deposited</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Inline JS for AJAX --}}
        <script>
            $(document).ready(function () {
                function loadSalesData(range = 'last_30_days') {
                    $.ajax({
                        url: "{{ route('sales.summary') }}",
                        method: 'GET',
                        data: { range },
                        beforeSend: function () {
                            $('#notPaid, #paid, #deposited').text('Loading...');
                        },
                        success: function (res) {
                            $('#notPaid').text(`$${res.not_paid}`);
                            $('#paid').text(`$${res.paid}`);
                            $('#deposited').text(`$${res.deposited}`);
                        },
                        error: function (xhr) {
                            console.error(xhr.responseText);
                            $('#notPaid, #paid, #deposited').text('Error');
                        }
                    });
                }

                // On page load
                loadSalesData();

                // On filter change
                $('#dateFilter').change(function () {
                    const range = $(this).val();
                    loadSalesData(range);
                });
            });
        </script>


        <div class="col-lg-4">
            <div class="panel-rounded h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">BANK ACCOUNTS</h6>
                    <select id="bankDateFilter" class="form-select w-auto">
                        <option value="last_30_days">Last 30 days</option>
                        <option value="last_month">Last Month</option>
                        <option value="last_quarter">Last Quarter</option>
                        <option value="last_year">Last Year</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Today's bank balance</label>
                    <div class="d-flex align-items-center gap-2">
                        <div id="todayBankBalance" class="h4 mb-0 text-success">+$0.00</div>
                        <small id="bankBalanceUpdated" class="text-muted">Updated: --</small>
                    </div>
                </div>

                <ul id="bankAccountsList" class="list-group list-group-flush" style="height: 150px; overflow: auto;">
                    <!-- Dynamic accounts will populate here -->
                </ul>
            </div>
        </div>


        <script>
            $(document).ready(function () {
                function loadBankAccounts(range = 'last_30_days') {
                    $.ajax({
                        url: "{{ route('bank.accounts.summary') }}",
                        method: 'GET',
                        data: { range },
                        beforeSend: function () {
                            $('#todayBankBalance').text('Loading...');
                            $('#bankAccountsList').html('<li class="list-group-item text-center">Loading...</li>');
                        },
                        success: function (res) {
                            $('#todayBankBalance').text(`$${res.today_total}`);
                            $('#bankBalanceUpdated').text(`Updated: ${res.updated_at}`);

                            const list = res.accounts.map(acc => {
                                let badgeClass = acc.balance >= 0 ? 'bg-success' : 'bg-danger';
                                let sign = acc.balance >= 0 ? '+' : '-';

                                let lastTxnText = acc.last_credit > 0
                                    ? `Last credit: $${acc.last_credit}`
                                    : (acc.last_debit > 0 ? `Last debit: $${acc.last_debit}` : 'No recent transaction');

                                return `
                                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <div class="fw-bold">${acc.name}</div>
                                                            <div class="small text-muted">${lastTxnText}</div>
                                                        </div>
                                                        <span class="badge ${badgeClass} rounded-pill">${sign} $${Math.abs(acc.balance)}</span>
                                                    </li>
                                                `;
                            }).join('');

                            $('#bankAccountsList').html(list);
                        },
                        error: function () {
                            $('#bankAccountsList').html('<li class="list-group-item text-center text-danger">Failed to load data</li>');
                        }
                    });
                }

                // Initial load
                loadBankAccounts();

                // On date filter change
                $('#bankDateFilter').change(function () {
                    const range = $(this).val();
                    loadBankAccounts(range);
                });
            });
        </script>


        <!-- Profit & Loss Panel -->
        <div class="col-lg-4">
            <div class="panel-rounded h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Profit & Loss</h6>
                    <select id="plDateFilter" class="form-select w-auto">
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="year_to_date">Year to Date</option>
                    </select>
                </div>

                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted" id="plPeriodText">Net Profit For Current Month</small>
                            <div id="plNetProfit" class="h4 mb-0 text-danger">- $0</div>
                            <div class="d-flex align-items-center gap-1">
                                <i class="fa fa-chevron-down text-warning mr-1"></i>
                                <div class="text-muted">
                                    <span id="plTrendText" class="text-warning">0%</span> from prior month
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <canvas id="plChart" style="max-height:160px;"></canvas>
            </div>
        </div>


        <script>
            $(document).ready(function () {
                let plChart;

                function loadPL(range = 'this_month') {
                    $.ajax({
                        url: "{{ route('profit.loss.summary') }}",
                        method: 'GET',
                        data: { range },
                        beforeSend: function () {
                            $('#plNetProfit').text('Loading...');
                            $('#plTrendText').text('--');
                        },
                        success: function (res) {
                            // Update Net Profit
                            const netProfitSign = res.net_profit >= 0 ? '+' : '-';
                            const netProfitClass = res.net_profit >= 0 ? 'text-success' : 'text-danger';
                            $('#plNetProfit').attr('class', `h4 mb-0 ${netProfitClass}`)
                                .text(`${netProfitSign} $${Math.abs(res.net_profit)}`);
                            $('#plTrendText').text(`${res.trend}%`);

                            // Chart Data
                            const plCtx = document.getElementById('plChart').getContext('2d');

                            if (plChart) plChart.destroy();

                            plChart = new Chart(plCtx, {
                                type: 'bar',
                                data: {
                                    labels: ['Income', 'Expenses'],
                                    datasets: [{
                                        label: 'Amount',
                                        data: [res.revenue, res.expenses],
                                        backgroundColor: [
                                            getComputedStyle(document.documentElement).getPropertyValue('--bs-success').trim(),
                                            getComputedStyle(document.documentElement).getPropertyValue('--bs-danger').trim()
                                        ]
                                    }]
                                },
                                options: {
                                    indexAxis: 'y',
                                    responsive: true,
                                    plugins: {
                                        legend: { display: false }
                                    },
                                    scales: {
                                        x: { beginAtZero: true }
                                    }
                                }
                            });
                        },
                        error: function () {
                            $('#plNetProfit').text('Error');
                            $('#plTrendText').text('--');
                        }
                    });
                }

                // Initial load
                loadPL();

                // On date filter change
                $('#plDateFilter').change(function () {
                    const range = $(this).val();
                    loadPL(range);
                });
            });
        </script>



        <!-- Expenses card -->
        <div class="col-lg-4">
            <div class="panel-rounded h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Expenses</h6>
                    <select id="expDateFilter" class="form-select w-auto">
                        <option value="last_30_days">Last 30 days</option>
                        <option value="last_quarter">Last Quarter</option>
                        <option value="last_year">Last Year</option>
                    </select>
                </div>

                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted" id="expPeriodText">Spending Overview</small>
                            <div id="expTotal" class="h5 mb-0 text-secondary">$0</div>
                        </div>
                        <div class="text-end small text-muted">Compared to last period</div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div style="width: 45%;">
                        <canvas id="expChart" style="max-height:200px;"></canvas>
                    </div>
                    <div style="width: 55%;">
                        <ul id="expCategoryList" class="list-unstyled small mb-0"></ul>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function () {
                let expChart;

                function loadExpenses(range = 'last_30_days') {
                    $.ajax({
                        url: "{{ route('expenses.summary') }}",
                        method: 'GET',
                        data: { range },
                        beforeSend: function () {
                            $('#expTotal').text('Loading...');
                            $('#expCategoryList').html('');
                        },
                        success: function (res) {
                            $('#expTotal').text(`$${res.total}`);
                            $('#expPeriodText').text(res.periodText);

                            const labels = Object.keys(res.categories);
                            const data = Object.values(res.categories);

                            // Update category list
                            let listHtml = '';
                            labels.forEach((label, index) => {
                                listHtml += `<li class="mb-2">${label} <span class="float-end text-muted">$${data[index]}</span></li>`;
                            });
                            $('#expCategoryList').html(listHtml);

                            // Update Chart
                            const ctx = document.getElementById('expChart').getContext('2d');
                            if (expChart) expChart.destroy();

                            expChart = new Chart(ctx, {
                                type: 'pie',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        data: data,
                                        backgroundColor: [
                                            '#0d6efd', // Bootstrap primary
                                            '#198754', // success
                                            '#dc3545', // danger
                                            '#ffc107', // warning
                                            '#6c757d', // secondary
                                            '#0dcaf0', // info
                                            '#fd7e14', // orange
                                            '#adb5bd', // gray
                                        ]
                                    }]
                                },
                                options: {
                                    responsive: true,
                                }
                            });
                        },
                        error: function () {
                            $('#expTotal').text('Error');
                            $('#expCategoryList').html('');
                        }
                    });
                }

                loadExpenses(); // initial load

                $('#expDateFilter').change(function () {
                    const range = $(this).val();
                    loadExpenses(range);
                });
            });
        </script>

    </div>
@endsection