/**
 * Referrals settings component
 */
"use strict";
/* global bootstrap, trans */

$(function () {
    // Initiate copy tooltip
    var copyButton = document.getElementById('copy-button');
    if (copyButton) {
        bootstrap.Tooltip.getOrCreateInstance(copyButton);
    }

    // When the copy button is clicked, select the value of the text box, attempt
    // to execute the copy command, and trigger event to update tooltip message
    // to indicate whether the text was successfully copied.
    $('#copy-button').on('click', function() {
        var input = document.querySelector('#copy-input');
        try {
            navigator.clipboard.writeText(input.value).then(
                function () {
                    $('#copy-button').trigger('copied', [trans('Copied!')]);
                    // success
                })
                .catch(
                    function () {
                        $('#copy-button').trigger('copied', [trans('Copied!')]);
                        // error
                    });
        } catch (err) {
            $('#copy-button').trigger('copied', [trans('Copied!')]);
        }
    });

    // Handler for updating the tooltip message.
    $('#copy-button').on('copied', function(event, message) {
        var tooltip = bootstrap.Tooltip.getInstance(this);
        if (tooltip) {
            tooltip.dispose();
        }
        $(this).attr('title', message);
        bootstrap.Tooltip.getOrCreateInstance(this).show();
    });
});
