<div class="col-4 px-1">
    <div class="bg-gradient-primary text-white py-2 my-1 px-3 rounded">
        <div class="d-flex justify-content-between">
            <div class="align-items-center d-none d-md-flex">
                <div>
                    <div class="fw-bold"><span>@</span>{{$tip->sender->username}} </div>
                    <div class="small">{{__("has tipped")}}</div>
                </div>
            </div>
            <div class="d-flex align-items-center">
                <div class="fs-4 fw-bolder mb-0">
                    {{\App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount($tip->decodedTaxes ? ($tip->decodedTaxes['subtotal'] ?? $tip->amount) : $tip->amount)}}
                </div>
            </div>
        </div>
    </div>
</div>
