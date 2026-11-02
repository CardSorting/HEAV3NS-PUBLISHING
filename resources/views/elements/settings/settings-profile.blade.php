@php
    $profileAiPanelOpen = $errors->has('ai') || $errors->has('ai.tone') || $errors->has('ai.length') || $errors->has('ai.share_profile') || $errors->has('ai.traits') || $errors->has('ai.traits.*');
    $profileSocialPanelOpen = $errors->has('social_links') || $errors->has('social_links.*.platform') || $errors->has('social_links.*.value');
@endphp

@if(!Auth::user()->email_verified_at) @include('elements.resend-verification-email-box') @endif

<form method="POST" action="{{route('my.settings.profile.save',['type'=>'profile'])}}">
    @csrf
    @include('elements.dropzone-dummy-element')
    <div class="mb-4">
        <div class="">
            <div class="card profile-cover-bg">
                <img class="card-img-top centered-and-cropped" src="{{Auth::user()->cover}}">

                <div class="profile-asset-loading d-none">
                    <div class="shimmer"></div>
                </div>

                <div class="card-img-overlay d-flex justify-content-center align-items-center">
                    <div class="actions-holder d-none">

                        <div class="d-flex">
                            @if(getSetting('ai.images_enabled'))
                                <span class="h-pill h-pill-media h-pill-media--light pointer-cursor me-1"
                                      onclick="ProfileSettings.generateUserAsset('cover')"
                                      data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Generate cover with AI')}}">
                                      @include('elements.icon',['icon'=>'hardware-chip-outline','variant'=>'medium'])
                                  </span>
                            @endif
                            <span class="h-pill h-pill-media h-pill-media--light pointer-cursor me-1 upload-button" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Upload cover image')}}">
                             @include('elements.icon',['icon'=>'image','variant'=>'medium'])
                        </span>
                            <span class="h-pill h-pill-media h-pill-media--light pointer-cursor" onclick="ProfileSettings.removeUserAsset('cover')" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Remove cover image')}}">
                            @include('elements.icon',['icon'=>'close','variant'=>'medium'])
                        </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="card avatar-holder">
                <img class="card-img-top" src="{{Auth::user()->avatar}}">

                <div class="profile-asset-loading d-none">
                    <div class="shimmer"></div>
                </div>

                <div class="card-img-overlay d-flex justify-content-center align-items-center">
                    <div class="actions-holder d-none">
                        <div class="d-flex">
                            @if(getSetting('ai.images_enabled'))
                                <span class="h-pill h-pill-media h-pill-media--light pointer-cursor me-1"
                                      onclick="ProfileSettings.generateUserAsset('avatar')"
                                      data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Generate avatar with AI')}}">
                                      @include('elements.icon',['icon'=>'hardware-chip-outline','variant'=>'medium'])
                                  </span>
                            @endif
                            <span class="h-pill h-pill-media h-pill-media--light pointer-cursor me-1 upload-button" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Upload avatar')}}">
                            @include('elements.icon',['icon'=>'image','variant'=>'medium'])
                        </span>
                            <span class="h-pill h-pill-media h-pill-media--light pointer-cursor" onclick="ProfileSettings.removeUserAsset('avatar')" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Remove avatar')}}">
                             @include('elements.icon',['icon'=>'close','variant'=>'medium'])
                        </span>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    @if(session('success'))
        <div class="alert alert-dismissible alert-success text-white fw-bold mt-2" role="alert">
            {{session('success')}}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    <div class="mb-3">
        <label for="username">{{__('Username')}}</label>
        <input class="form-control {{ $errors->has('username') ? 'is-invalid' : '' }}" id="username" name="username" aria-describedby="emailHelp" value="{{ old('username', Auth::user()->username) }}">
        @if($errors->has('username'))
            <span class="invalid-feedback" role="alert">
                <strong>{{$errors->first('username')}}</strong>
            </span>
        @endif

    </div>
    <div class="mb-3">
        <label for="name">{{__('Full name')}}</label>
        <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}" id="name" name="name" aria-describedby="emailHelp" value="{{ old('name', Auth::user()->name) }}">
        @if($errors->has('name'))
            <span class="invalid-feedback" role="alert">
                <strong>{{$errors->first('name')}}</strong>
            </span>
        @endif
    </div>
    <div class="mb-3">
        <div class="d-flex justify-content-between">
            <label for="bio">
                {{__('Bio')}}
            </label>
            <div>
                @if(getSetting('ai.text_enabled'))
                    <a href="javascript:void(0)"
                       class="ai-suggest-link"
                       data-ai-type="profile_bio"
                       data-loading-text="{{ __('Generating') }}">
                        {{ __('AI Suggestion') }}
                    </a>
                @endif
            </div>
        </div>
        <textarea class="form-control {{ $errors->has('bio') ? 'is-invalid' : '' }}" id="bio" name="bio" rows="3" spellcheck="false">{{ old('bio', Auth::user()->bio) }}</textarea>
        @if($errors->has('bio'))
            <span class="invalid-feedback" role="alert">
                <strong>{{$errors->first('bio')}}</strong>
            </span>
        @endif
    </div>
    <div class="mb-3">
        <label for="birthdate">{{__('Birthdate')}}</label>
        <input type="date" class="form-control {{ $errors->has('location') ? 'is-invalid' : '' }}" id="birthdate" name="birthdate" aria-describedby="emailHelp"  value="{{ old('birthdate', Auth::user()->birthdate) }}" max="{{$minBirthDate}}">
        @if($errors->has('birthdate'))
            <span class="invalid-feedback" role="alert">
                <strong>{{$errors->first('birthdate')}}</strong>
            </span>
        @endif
    </div>

    <div class="d-flex flex-row">
        <div class="{{getSetting('profiles.allow_gender_pronouns') ? 'w-50' : 'w-100'}} pe-2">
            <div class="mb-3">
                <label for="gender">{{__('Gender')}}</label>
                <select class="form-select" id="gender" name="gender" >
                    <option value=""></option>
                    @foreach($genders as $gender)
                        <option value="{{$gender->id}}" {{ (string) old('gender', Auth::user()->gender_id) === (string) $gender->id ? 'selected' : '' }}>{{__($gender->gender_name)}}</option>
                    @endforeach
                </select>
                @if($errors->has('gender'))
                    <span class="invalid-feedback" role="alert">
                <strong>{{$errors->first('gender')}}</strong>
            </span>
                @endif
            </div>
        </div>

        @if(getSetting('profiles.allow_gender_pronouns'))
            <div class="w-50 ps-2">
                <div class="mb-3">
                    <label for="pronoun">{{__('Gender pronoun')}}</label>
                    <input class="form-control {{ $errors->has('location') ? 'is-invalid' : '' }}" id="pronoun" name="pronoun" aria-describedby="emailHelp"  value="{{ old('pronoun', Auth::user()->gender_pronoun) }}">
                    @if($errors->has('pronoun'))
                        <span class="invalid-feedback" role="alert">
                        <strong>{{$errors->first('pronoun')}}</strong>
                    </span>
                    @endif
                </div>
            </div>
        @endif

    </div>

    <div class="d-flex flex-row">
        <div class="mb-3 w-50 pe-2">
            <label for="country">{{__('Country')}}</label>
            <select class="form-select" id="country" name="country" >
                <option value=""></option>
                @foreach($countries as $country)
                    <option value="{{$country->id}}" {{ (string) old('country', Auth::user()->country_id) === (string) $country->id ? 'selected' : '' }}>{{__($country->name)}}</option>
                @endforeach
            </select>
            @if($errors->has('country'))
                <span class="invalid-feedback" role="alert">
                    <strong>{{$errors->first('country')}}</strong>
                </span>
            @endif
        </div>

        <div class="mb-3 w-50 ps-2">
            <label for="location">{{__('Location')}}</label>
            <input class="form-control {{ $errors->has('location') ? 'is-invalid' : '' }}" id="location" name="location" aria-describedby="emailHelp"  value="{{ old('location', Auth::user()->location) }}">
            @if($errors->has('location'))
                <span class="invalid-feedback" role="alert">
                    <strong>{{$errors->first('location')}}</strong>
                </span>
            @endif
        </div>
    </div>

    <div class="mb-3">
        <label for="website" value="{{Auth::user()->website}}">{{__('Website URL')}}</label>
        <input type="url" class="form-control {{ $errors->has('website') ? 'is-invalid' : '' }}" id="website" name="website" aria-describedby="emailHelp" value="{{ old('website', Auth::user()->website) }}">
        @if($errors->has('website'))
            <span class="invalid-feedback" role="alert">
                <strong>{{$errors->first('website')}}</strong>
            </span>
        @endif
    </div>

    @if(getSetting('ai.text_enabled'))
        <div class="settings-collapse-panel mt-3 mb-3"
             data-theme-light-class="settings-collapse-panel-light"
             data-theme-dark-class="settings-collapse-panel-dark">
            <button
                type="button"
                class="settings-collapse-panel-header"
                data-bs-toggle="collapse"
                data-bs-target="#profile-ai-options"
                aria-expanded="{{$profileAiPanelOpen ? 'true' : 'false'}}"
                aria-controls="profile-ai-options"
            >
                <span class="settings-collapse-panel-copy">
                    <span class="fw-bold d-block">{{__('AI preferences')}}</span>
                    <span class="text-muted small d-block">{{__('Tune AI suggestions for your profile content.')}}</span>
                </span>
                <span class="settings-collapse-panel-caret" aria-hidden="true">
                    @include('elements.icon',['icon'=>'chevron-down-outline','centered'=>false])
                </span>
            </button>

            <div id="profile-ai-options" class="collapse {{$profileAiPanelOpen ? 'show' : ''}}">
                <div class="settings-collapse-panel-body">
                    @include('elements.settings.ai-settings')
                </div>
            </div>
        </div>
    @endif

    @if(getSetting('profiles.social_links_enabled'))
        <div class="settings-collapse-panel mt-3 mb-3"
             data-theme-light-class="settings-collapse-panel-light"
             data-theme-dark-class="settings-collapse-panel-dark">
            <button
                type="button"
                class="settings-collapse-panel-header"
                data-bs-toggle="collapse"
                data-bs-target="#profile-social-links-options"
                aria-expanded="{{$profileSocialPanelOpen ? 'true' : 'false'}}"
                aria-controls="profile-social-links-options"
            >
                <span class="settings-collapse-panel-copy">
                    <span class="fw-bold d-block">{{__('Social links')}}</span>
                    <span class="text-muted small d-block">{{__('Manage links shown on your public profile.')}}</span>
                </span>
                <span class="settings-collapse-panel-caret" aria-hidden="true">
                    @include('elements.icon',['icon'=>'chevron-down-outline','centered'=>false])
                </span>
            </button>

            <div id="profile-social-links-options" class="collapse {{$profileSocialPanelOpen ? 'show' : ''}}">
                <div class="settings-collapse-panel-body">
                    @include('elements.settings.settings-social-network-selector')
                </div>
            </div>
        </div>
    @endif

    @if(getSetting('profiles.spotify_enabled'))
        <div class="settings-collapse-panel mt-3 mb-3"
             data-theme-light-class="settings-collapse-panel-light"
             data-theme-dark-class="settings-collapse-panel-dark">
            <button
                type="button"
                class="settings-collapse-panel-header"
                data-bs-toggle="collapse"
                data-bs-target="#profile-spotify-options"
                aria-expanded="false"
                aria-controls="profile-spotify-options"
            >
                <span class="settings-collapse-panel-copy">
                    <span class="fw-bold d-block">{{__('Spotify')}}</span>
                    <span class="text-muted small d-block">
                        {{($spotifyAccount ?? null)
                            ? __('Connected to :name.', ['name' => $spotifyAccount->display_name ?: '@'.$spotifyAccount->spotify_id])
                            : __('Connect Spotify to show top artists and an anthem.')}}
                    </span>
                </span>
                <span class="settings-collapse-panel-caret" aria-hidden="true">
                    @include('elements.icon',['icon'=>'chevron-down-outline','centered'=>false])
                </span>
            </button>

            <div id="profile-spotify-options" class="collapse">
                <div class="settings-collapse-panel-body">
                    @include('elements.settings.settings-spotify-inline', ['spotifyAccount' => $spotifyAccount ?? null])
                </div>
            </div>
        </div>
    @endif

    <button class="btn btn-primary w-100 rounded me-0" type="submit">{{__('Save')}}</button>
</form>
