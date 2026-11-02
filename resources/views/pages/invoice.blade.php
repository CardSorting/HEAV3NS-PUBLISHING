@php
    $invoiceDateFormat = \App\Providers\InvoiceServiceProvider::getDateFormat();
@endphp
<!doctype html>
<html class="h-100"
      dir="{{ GenericHelper::getSiteDirection() }}"
      lang="{{ session('locale') }}"
      data-bs-theme="{{ GenericHelper::getSiteTheme() }}"
      data-theme="{{ GenericHelper::getSiteTheme() }}">
<head>
    @section('page_title', __('Invoice'))
    @section('styles')
        {!!
            Minify::stylesheet([
                '/css/pages/invoices.css',
             ])->withFullUrl()
        !!}
    @stop
    @include('template.head')
</head>
<body class="d-flex flex-column">
<div class="flex-fill invoice-body invoice-body-{{ GenericHelper::getSiteTheme() }}"
     data-theme-light-class="invoice-body-light"
     data-theme-dark-class="invoice-body-dark">
    <div class="container">
        <div class="invoice-logo text-center">
            <img class="brand-logo text-center"
                 src="{{ GenericHelper::getCurrentThemeLogo() }}"
                 data-theme-light-src="{{ asset(getSetting('site.light_logo')) }}"
                 data-theme-dark-src="{{ asset(getSetting('site.dark_logo')) }}"
                 alt="{{ __('Site logo') }}">
        </div>
        @if(isset($invoice->decodedData))
            <div class="row invoice row-printable d-flex justify-content-center align-items-center mb-5">
                <div class="col-md-10 p-4 invoice-content rounded invoice-border-{{ GenericHelper::getSiteTheme() }}"
                     data-theme-light-class="invoice-border-light"
                     data-theme-dark-class="invoice-border-dark">
                    <!-- col-lg-12 start here -->
                    <div class="panel panel-default plain" id="dash_0">
                        <!-- Start .panel -->
                        <div class="panel-body p30 mt-2">
                            <!-- Start .row -->
                            <div class="row">
                            <!-- col-lg-6 end here -->
                                <div class="col-lg-12">
                                    <!-- col-lg-12 start here -->
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <div class="invoice-details mt25">
                                                <div class="well">
                                                    <ul class="list-unstyled mb0">
                                                        <li><strong>{{__('Invoice')}}</strong>
                                                            #{{$invoice->decodedData['invoicePrefix'] ? $invoice->decodedData['invoicePrefix']. '_' : ''  }}{{$invoice->invoice_id}}
                                                        </li>
                                                        <li><strong>{{__('Invoice')}}
                                                                {{__('Date')}}
                                                                :</strong> {{ \Carbon\Carbon::parse($invoice->created_at)->format($invoiceDateFormat)}}
                                                        </li>
                                                        <li><strong>{{__('Due')}}
                                                                {{__('Date')}}
                                                                :</strong> {{ \Carbon\Carbon::parse($invoice->created_at)->format($invoiceDateFormat)}}
                                                    </ul>
                                                </div>
                                            </div>
                                            <ul class="list-unstyled text-break">
                                                <li><strong>{{__('Invoiced To')}}</strong></li>
                                                <li>{{$invoice->decodedData['billingDetails']['receiverFirstName']}} {{$invoice->decodedData['billingDetails']['receiverLastName']}}</li>
                                                <li>{{$invoice->decodedData['billingDetails']['receiverBillingAddress']}},
                                                    {{$invoice->decodedData['billingDetails']['receiverState']}},
                                                    {{$invoice->decodedData['billingDetails']['receiverPostcode']}}
                                                </li>
                                                <li>{{$invoice->decodedData['billingDetails']['receiverCity']}}</li>
                                                <li>{{$invoice->decodedData['billingDetails']['receiverCountryName']}}</li>
                                            </ul>
                                        </div>
                                        <div class="col-6">
                                            <ul class="list-unstyled text-end text-break">
                                                <li><strong>{{__('Invoice From')}}</strong></li>
                                                <li>{{$invoice->decodedData['billingDetails']['senderName']}}</li>
                                                <li>{{$invoice->decodedData['billingDetails']['senderAddress']}} {{$invoice->decodedData['billingDetails']['senderState']}} {{$invoice->decodedData['billingDetails']['senderPostcode']}}</li>
                                                <li>{{$invoice->decodedData['billingDetails']['senderCity']}}</li>
                                                <li>{{$invoice->decodedData['billingDetails']['senderCountry']}}</li>
                                                <li>{{$invoice->decodedData['billingDetails']['senderCompanyNumber'] ? __('VAT Number').' '.$invoice->decodedData['billingDetails']['senderCompanyNumber'] : '' }}</li>
                                            </ul>
                                        </div>


                                    </div>
                                    <div class="invoice-items mb-3">
                                        <div class="table-responsive"
                                             tabindex="0">
                                            <table class="table table-bordered">
                                                <thead>

                                                </thead>
                                                <tbody>

                                                </tbody>
                                                <tfoot>
                                                <tr>
                                                    <th colspan="2" class="per70 text-center">{{__('Description')}}</th>
                                                    {{--<th class="per5 text-center">Qty</th>--}}
                                                    <th class="per25 text-center">{{__('Total')}}</th>
                                                </tr>
                                                <tr>
                                                    <td class="text-center"
                                                        colspan="2">{{\App\Providers\InvoiceServiceProvider::getInvoiceDescriptionByTransaction($invoice->transaction)}}
                                                    </td>
                                                    {{--<td class="text-center">1</td>--}}
                                                    <td class="text-center">
                                                        {{\App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount($invoice->decodedData['subtotal'])}}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th colspan="2" class="text-end">{{__('Total taxes')}}:</th>
                                                    <th class="text-center">{{ \App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount($invoice->decodedData['taxesTotalAmount'])}}</th>
                                                </tr>
                                                @if(isset($invoice->decodedData['taxes']) && isset($invoice->decodedData['taxes']['data']))
                                                    @foreach($invoice->decodedData['taxes']['data'] as $tax)
                                                        <tr>
                                                            <th colspan="2" class="text-end">
                                                                {{$tax['taxName']}}
                                                                @if(isset($tax['taxPercentage']))
                                                                    ({{$tax['taxPercentage']}}
                                                                    %{{$tax['taxType'] === 'inclusive' ? ' incl.' : ''}})
                                                                @endif
                                                            </th>
                                                            <th class="text-center">
                                                                {{\App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount($tax['taxAmount'])}}
                                                            </th>
                                                        </tr>
                                                    @endforeach
                                                @endif
                                                <tr>
                                                    <th colspan="2" class="text-end">{{__('Total:')}}</th>
                                                    <th class="text-center">{{\App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount($invoice->decodedData['totalAmount'])}}</th>
                                                </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <!-- col-lg-12 end here -->
                            </div>
                            <!-- End .row -->
                        </div>
                        <div class="invoice-footer mt25">
                            <p class="text-center">{{__('Generated on')}} {{ \Carbon\Carbon::parse($invoice->created_at)->format($invoiceDateFormat)}} </p>
                            <p class="d-flex justify-content-center align-items-center">
                                <a href="{{route('my.settings',['type'=>'payments'])}}" class="me-3">{{__("Back")}}</a>
                                <a href="#" onclick="window.print()" class="btn btn-primary btn-sm m-0"> {{__('Print')}}</a>
                            </p>
                        </div>
                    </div>
                    <!-- End .panel -->
                </div>
                <!-- col-lg-12 end here -->
            </div>
        @else
            <div class="d-flex justify-content-center align-content-center my-5">
                <p class="h5">⚠ {{__("Invalid invoice data provided")}}</p>
            </div>
        @endif
    </div>
</div>

@include('template.jsVars')
@include('template.jsAssets')

</body>
</html>
