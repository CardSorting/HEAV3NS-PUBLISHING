<div class="modal fade"
     tabindex="-1"
     role="dialog"
     id="language-selector-dialog"
     aria-labelledby="language-selector-title"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="language-selector-title">{{__('Change language')}}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="language_code">{{__('Select the language you want to use.')}}</label>
                <div class="input-group">
                    <select class="form-select" id="language_code" name="language_code">
                        @foreach(LocalesHelper::getAvailableLanguages() as $languageCode)
                            @if(LocalesHelper::getLanguageName($languageCode))
                                <option value="{{$languageCode}}" {{LocalesHelper::getUserPreferredLocale(request()) == $languageCode ? 'selected' : ''}}>{{ucfirst(__(LocalesHelper::getLanguageName($languageCode)))}}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                </div>
                <div>
                    <button type="button" class="btn btn-primary mb-0" onclick="setUserLanguage()">{{__('Save')}}</button>
                </div>
            </div>
        </div>
    </div>
</div>
