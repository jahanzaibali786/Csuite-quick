{{-- Expense Tabs --}}
<div class="mt-3">
    <div id="printableArea">
        <div class="row">
            <div class="col-12">
                <ul class="nav nav-tabs" id="expenseTabs" role="tablist" style="padding: 8px 8px">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ Request::route()->getName() == 'expense.index' || Request::route()->getName() == 'expense.create' || Request::route()->getName() == 'expense.edit' || Request::route()->getName() == 'expense.show' ? 'active' : '' }}"
                            href="{{ route('expense.index') }}">
                            <i class="ti ti-receipt me-2"></i>{{ __('Expenses') }}
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ Request::route()->getName() == 'bill.index' || Request::route()->getName() == 'bill.create' || Request::route()->getName() == 'bill.edit' || Request::route()->getName() == 'bill.show' ? 'active' : '' }}"
                            href="{{ route('bill.index') }}">
                            <i class="ti ti-file-invoice me-2"></i>{{ __('Bills') }}
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ Request::route()->getName() == 'payment.index' || Request::route()->getName() == 'payment.create' || Request::route()->getName() == 'payment.edit' ? 'active' : '' }}"
                            href="{{ route('payment.index') }}">
                            <i class="ti ti-credit-card me-2"></i>{{ __('Bill Payments') }}
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ Request::segment(1) == 'vender' ? 'active' : '' }}"
                            href="{{ route('vender.index') }}">
                            <i class="ti ti-building-store me-2"></i>{{ __('Vendors') }}
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
    .nav-tabs {
        border-bottom: 2px solid #e0e0e0;
        gap: 0;
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none; /* Hide scrollbar for Firefox */
        padding: 0 8px;
        background: transparent;
    }

    /* Hide scrollbar for Chrome, Safari and Opera */
    .nav-tabs::-webkit-scrollbar {
        display: none;
    }

    .nav-tabs .nav-item {
        flex-shrink: 0;
        margin-bottom: 0;
    }

    .nav-tabs .nav-link {
        border: none;
        border-radius: 0;
        color: #6c757d;
        font-weight: 500;
        padding: 12px 16px;
        transition: all 0.3s ease;
        background-color: transparent;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
        min-width: fit-content;
        position: relative;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
    }

    .nav-tabs .nav-link:hover {
        color: #28a745;
        background-color: transparent;
        border-bottom: 3px solid #28a745;
    }

    .nav-tabs .nav-link.active {
        color: #28a745 !important;
        background-color: transparent !important;
        border-bottom: 3px solid #28a745 !important;
        font-weight: 600;
    }

    .nav-tabs .nav-link i {
        font-size: 1rem;
        margin-right: 6px;
    }

    @media (max-width: 768px) {
        .nav-tabs {
            gap: 0;
            padding: 0 4px;
        }

        .nav-tabs .nav-link {
            padding: 10px 12px;
            font-size: 0.875rem;
        }

        .nav-tabs .nav-link i {
            font-size: 0.875rem;
            margin-right: 4px;
        }
    }

    @media (max-width: 576px) {
        .nav-tabs .nav-link {
            padding: 8px 10px;
            font-size: 0.8rem;
        }

        .nav-tabs .nav-link i {
            display: none; /* Hide icons on very small screens to save space */
        }
    }
</style>

<script>
    function showComingSoon() {
        if (typeof show_toastr !== 'undefined') {
            show_toastr('info', '{{ __('This feature is coming soon!') }}', 'info');
        } else {
            alert('{{ __('This feature is coming soon!') }}');
        }
    }
</script>
