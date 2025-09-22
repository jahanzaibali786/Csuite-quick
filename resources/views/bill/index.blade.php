@extends('layouts.admin')
@section('page-title')
    {{__('Manage Bills')}}
@endsection
@push('script-page')
    <script>
        $('.copy_link').click(function (e) {
            e.preventDefault();
            var copyText = $(this).attr('href');

            document.addEventListener('copy', function (e) {
                e.clipboardData.setData('text/plain', copyText);
                e.preventDefault();
            }, true);

            document.execCommand('copy');
            show_toastr('success', 'Url copied to clipboard', 'success');
        });
    </script>
@endpush
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{route('dashboard')}}">{{__('Dashboard')}}</a></li>
    <li class="breadcrumb-item">{{__('Bill')}}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        <a href="{{ route('bill.export') }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="{{__('Export')}}">
            <i class="ti ti-file-export"></i>
        </a>
        @can('create bill')
            <a href="{{ route('bill.create',0) }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="{{__('Create')}}">
                {{__('Create New Bill')}}
                <i class="ti ti-plus"></i>
            </a>
        @endcan
    </div>
@endsection


@section('content')
    {{-- tabs --}}
    @include('expense.expense-tabs')
{{-- Filters Dropdown --}}
<div class="dropdown mt-4 mb-2">
    <button class="btn btn-outline-primary dropdown-toggle d-flex align-items-center" type="button"
        id="filtersDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="ti ti-filter me-1"></i> {{ __('Filters') }}
    </button>

    <div class="dropdown-menu p-3" style="min-width: 350px;">
        <div class="card shadow-none border-0">
            <div class="card-body p-0">
                {{ Form::open(['route' => ['bill.index'], 'method' => 'GET', 'id' => 'frm_submit']) }}
                <div class="row">

                    {{-- Bill Date --}}
                    <div class="col-12 mb-3">
                        {{ Form::label('bill_date', __('Bill Date'), ['class' => 'form-label']) }}
                        {{ Form::text('bill_date', request('bill_date'), [
                            'class' => 'form-control month-btn',
                            'id' => 'pc-daterangepicker-1',
                            'readonly'
                        ]) }}
                    </div>

                    {{-- Status --}}
                    <div class="col-12 mb-3">
                        {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
                        {{ Form::select('status', ['' => __('Select Status')] + $status, request('status'), [
                            'class' => 'form-control select'
                        ]) }}
                    </div>

                    {{-- Buttons --}}
                    <div class="col-12 d-flex justify-content-between">
                        <a href="{{ route('bill.index') }}"
                            class="btn btn-outline-secondary btn-sm"
                            data-bs-toggle="tooltip" title="{{ __('Reset') }}">
                            <i class="ti ti-trash-off"></i> {{ __('Reset') }}
                        </a>

                        <button type="submit" class="btn btn-success btn-sm"
                            data-bs-toggle="tooltip" title="{{ __('Apply') }}">
                            <i class="ti ti-search"></i> {{ __('Apply') }}
                        </button>
                    </div>

                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>



    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                            <tr>
                                <th> {{__('Bill')}}</th>
                                <th> {{__('Category')}}</th>
                                <th> {{__('Bill Date')}}</th>
                                <th> {{__('Due Date')}}</th>
                                <th>{{__('Status')}}</th>
                                @if(Gate::check('edit bill') || Gate::check('delete bill') || Gate::check('show bill'))
                                    <th width="10%"> {{__('Action')}}</th>
                                @endif
                            </tr>
                            </thead>
                            <tbody>
                                @foreach ($bills as $bill)
                                <tr>
                                    <td class="Id">
                                        <a href="{{ route('bill.show',\Crypt::encrypt($bill->id)) }}" class="btn btn-outline-primary">{{ AUth::user()->billNumberFormat($bill->bill_id) }}</a>
                                    </td>
                                    <td>{{ !empty($bill->category)?$bill->category->name:'-'}}</td>
                                    <td>{{ Auth::user()->dateFormat($bill->bill_date) }}</td>
                                    <td>{{ Auth::user()->dateFormat($bill->due_date) }}</td>
                                    <td>
                                        @if($bill->status == 0)
                                            <span class="status_badge badge bg-secondary p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$bill->status]) }}</span>
                                        @elseif($bill->status == 1)
                                            <span class="status_badge badge bg-warning p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$bill->status]) }}</span>
                                        @elseif($bill->status == 2)
                                            <span class="status_badge badge bg-danger p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$bill->status]) }}</span>
                                        @elseif($bill->status == 3)
                                            <span class="status_badge badge bg-info p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$bill->status]) }}</span>
                                        @elseif($bill->status == 4)
                                            <span class="status_badge badge bg-primary p-2 px-3 rounded">{{ __(\App\Models\Invoice::$statues[$bill->status]) }}</span>
                                        @endif
                                    </td>
                                    @if(Gate::check('edit bill') || Gate::check('delete bill') || Gate::check('show bill'))
                                        <td class="Action">
                                            <span>
                                                @can('duplicate bill')
                                                    <div class="action-btn bg-primary ms-2">
                                                        {!! Form::open(['method' => 'get', 'route' => ['bill.duplicate', $bill->id],'id'=>'duplicate-form-'.$bill->id]) !!}

                                                        <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para " data-bs-toggle="tooltip" data-original-title="{{__('Duplicate')}}" data-bs-toggle="tooltip" title="{{__('Duplicate Bill')}}" data-original-title="{{__('Delete')}}" data-confirm="You want to confirm this action. Press Yes to continue or Cancel to go back" data-confirm-yes="document.getElementById('duplicate-form-{{$bill->id}}').submit();">
                                                        <i class="ti ti-copy text-white"></i>
                                                            {!! Form::close() !!}
                                                        </a>
                                                    </div>
                                                @endcan
                                                @can('show bill')
                                                    <div class="action-btn bg-info ms-2">
                                                        <a href="{{ route('bill.show',\Crypt::encrypt($bill->id)) }}" class="mx-3 btn btn-sm align-items-center" data-bs-toggle="tooltip" title="{{__('Show')}}" data-original-title="{{__('Detail')}}">
                                                            <i class="ti ti-eye text-white"></i>
                                                        </a>
                                                    </div>
                                                @endcan
                                                @can('edit bill')
                                                    <div class="action-btn bg-primary ms-2">
                                                        <a href="{{ route('bill.edit',\Crypt::encrypt($bill->id)) }}" class="mx-3 btn btn-sm align-items-center" data-bs-toggle="tooltip" title="Edit" data-original-title="{{__('Edit')}}">
                                                            <i class="ti ti-pencil text-white"></i>
                                                        </a>
                                                    </div>
                                                @endcan
                                                @can('delete bill')
                                                    <div class="action-btn bg-danger ms-2">
                                                        {!! Form::open(['method' => 'DELETE', 'route' => ['bill.destroy', $bill->id],'class'=>'delete-form-btn','id'=>'delete-form-'.$bill->id]) !!}
                                                        <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para" data-bs-toggle="tooltip" title="{{__('Delete')}}" data-original-title="{{__('Delete')}}" data-confirm="{{__('Are You Sure?').'|'.__('This action can not be undone. Do you want to continue?')}}" data-confirm-yes="document.getElementById('delete-form-{{$bill->id}}').submit();">
                                                            <i class="ti ti-trash text-white"></i>
                                                        </a>
                                                        {!! Form::close() !!}
                                                    </div>
                                                @endcan
                                            </span>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

