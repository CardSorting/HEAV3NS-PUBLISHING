<div class="modal fade" tabindex="-1" role="dialog" id="broadcaster-camera-settings-dialog" aria-labelledby="broadcaster-camera-settings-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="broadcaster-camera-settings-title">{{__("Camera settings")}}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{__('Close')}}"></button>
            </div>
            <div class="modal-body">
                <!-- Device selection controls -->
                <div id="device-controls">
                    <div class="mb-3">
                        <label class="form-label" for="cameraSelect">{{__("Camera")}}</label>
                        <select id="cameraSelect" class="form-select"></select>
                    </div>
                    <div>
                        <label class="form-label" for="microphoneSelect">{{__("Microphone")}}</label>
                        <select id="microphoneSelect" class="form-select"></select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary stream-save-btn" onclick="Broadcast.saveCameraSettings();">{{__('Save')}}</button>
            </div>
        </div>
    </div>
</div>
