@php($staffBadgeType = app(\App\Services\UserStaffBadge::class)->forUser($user))
@if($staffBadgeType === 'admin')
    @php($staffBadgeLabel = __('Admin'))
    <span class="user-staff-badge d-inline-flex align-items-center flex-shrink-0 ms-1 {{ $badgeClasses ?? '' }}"
          role="img"
          aria-label="{{ $staffBadgeLabel }}"
          data-bs-toggle="tooltip"
          data-bs-placement="top"
          title="{{ $staffBadgeLabel }}">
        @include('elements.icon', ['icon' => 'staff', 'centered' => true, 'classes' => '', 'variant' => $badgeVariant ?? ''])
    </span>
@endif
