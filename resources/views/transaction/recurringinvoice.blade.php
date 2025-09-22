@extends('layouts.admin')

@section('page-title')
    {{ __('Recurring Payments') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Recurring Payments') }}</li>
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('css/datatable/buttons.dataTables.min.css') }}">
@endpush

@push('script-page')
    <script type="text/javascript" src="{{ asset('js/html2pdf.bundle.min.js') }}"></script>
    <script src="{{ asset('js/datatable/jszip.min.js') }}"></script>
    <script src="{{ asset('js/datatable/pdfmake.min.js') }}"></script>
    <script src="{{ asset('js/datatable/vfs_fonts.js') }}"></script>
@endpush

@section('content')
    {{-- tabs --}}
    @include('transaction.transactions-tabs')

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body table-border-style mt-2">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Customer') }}</th>
                                    <th>{{ __('Invoice #') }}</th>
                                    <th>{{ __('Payments Count') }}</th>
                                    <th>{{ __('Total Paid') }}</th>
                                    <th>{{ __('Last Payment Date') }}</th>
                                    <th>{{ __('Category') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recurring as $key => $payments)
                                    @php
                                        $first = $payments->first();
                                        $customer = \App\Models\Customer::find($first->customer_id);
                                        $category = \App\Models\ProductServiceCategory::find($first->category_id);
                                    @endphp
                                    <tr>
                                        <td>{{ $customer?->name ?? '-' }}</td>
                                        <td>#{{ $first->invoice_id }}</td>
                                        <td>{{ $payments->count() }}</td>
                                        <td>{{ Auth::user()->priceFormat($payments->sum('amount')) }}</td>
                                        <td>{{ Auth::user()->dateFormat($payments->max('date')) }}</td>
                                        <td>{{ $category?->name ?? '-' }}</td>
                                    </tr>
                                @endforeach
                                @if($recurring->isEmpty())
                                    <tr>
                                        <td colspan="6" class="text-center">{{ __('No recurring payments found in last 2 months') }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
