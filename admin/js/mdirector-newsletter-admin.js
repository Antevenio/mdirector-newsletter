jQuery(document).ready(function ($) {
    'use strict';

    // Timepicker
    $('input.timepicker').timepicker({
        timeFormat: 'HH:mm',
        minTime: new Date(0, 0, 0, 0, 0, 0),
        maxTime: new Date(0, 0, 0, 23, 30, 0),
        startTime: new Date(0, 0, 0, 0, 0, 0),
        interval: 30
    });

    $('.dynamic-choice').on( 'click', function () {
        var $this = $(this),
            isChecked = $this.is(':checked');

        $this.parents('.choice-block').siblings('.choice-block').find('.subject-block')
            .toggleClass('disabled', isChecked)
            .find('input, select').prop('readonly', isChecked);

        $this.siblings('.subject-block')
            .toggleClass('disabled', !isChecked)
            .find('input, select').prop('readonly', !isChecked);
    });

    $('[data-toggle]').on('click', function() {
        var $this = $(this);
        $('#' + $this.data('toggle')).toggle($this.is(':checked'));
    } );
});
