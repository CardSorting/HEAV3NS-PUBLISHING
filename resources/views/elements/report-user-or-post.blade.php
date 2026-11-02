<div class="modal fade" tabindex="-1" role="dialog" id="report-user-post">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{__('Report user or post')}}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="reasonExamples">{{__('Reason')}}</label>
                    <select id="reasonExamples" class="form-select">
                        @foreach($reportStatuses as $status)
                            <option value="{{$status}}">{{__($status)}}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label" for="post_report_details">{{__('Details')}}</label>
                    <textarea class="form-control" id="post_report_details" rows="2"></textarea>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger submit-report-button">{{__('Report')}}</button>
            </div>
        </div>
    </div>
</div>
