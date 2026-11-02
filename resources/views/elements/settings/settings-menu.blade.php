<!-- Collapsible Menu -->
<div class="collapse d-lg-block settings-nav" id="settingsNav">
    @include('elements.account.sidebar-menu', [
        'menuItems' => GenericHelper::getSettingsMenuItems($availableSettings),
        'menuRoute' => 'my.settings',
        'menuActive' => $activeSettingsTab,
    ])
</div>
