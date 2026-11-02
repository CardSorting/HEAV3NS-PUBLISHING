"use strict";
/* global bootstrap */

$(function () {
    var menu = $('.settings-payments-filter-menu');
    var toggle = menu.find('.settings-payments-filter-toggle');
    var panel = menu.find('.app-filter-panel');

    menu.on('show.bs.dropdown', function () {
        var bounds = toggle[0].getBoundingClientRect();
        var availableHeight = Math.max(bounds.top, window.innerHeight - bounds.bottom) - 16;
        panel.toggleClass('dropdown-menu-end', window.getComputedStyle(this).direction !== 'rtl')
            .css('--filter-dropdown-available-height', Math.max(0, availableHeight) + 'px');
    });
    menu.on('click', 'form', function (event) {
        // Keep the form open while editing; Apply and Reset retain their normal navigation.
        event.stopPropagation();
    });
    menu.on('keydown', function (event) {
        if (event.key === 'Escape' && toggle.attr('aria-expanded') === 'true') {
            event.preventDefault();
            event.stopPropagation();
            bootstrap.Dropdown.getOrCreateInstance(toggle[0]).toggle();
            toggle[0].focus({preventScroll: true});
        } else if (event.target.tagName === 'SELECT') {
            // Bootstrap's menu keyboard navigation must not override native select controls.
            event.stopPropagation();
        }
    });
});
