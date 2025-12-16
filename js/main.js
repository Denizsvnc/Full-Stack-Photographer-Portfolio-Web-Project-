// Popup
var AJPopup = {
    init: function() {
        var self = this;
        $(document).on('click', '.aj-popup-trigger', function(e) {
            e.preventDefault();

            var $trigger = $(this);
            var popupTarget = $trigger.attr('data-target');
            var $popup;
            if ('' === popupTarget || ! popupTarget) {
                var message = $trigger.attr('data-message');
                if ( '' === message || ! message) {
                    // do nothing
                } else {
                    self.showMessage($trigger.attr('data-message'));
                }
            } else {
                $popup = $($trigger.attr('data-target'));
                self.addOverlay($popup);
            }
        });
    },
    addOverlay: function ($popup) {
        $('#aj-overlay').remove();
        $('.aj-popup').addClass('aj-hidden');
        $('<div></div>')
            .attr('id', 'aj-overlay')
            .width($(document).width())
            .height($(document).height())
            .click(function() {
                AJPopup.close();
            })
           .appendTo($('body'));

        $popup
            .appendTo('body')
            .removeClass('aj-hidden');
    },
    showMessage: function (message) {
        var $popup = $('<div></div>')
            .addClass('aj-popup')
            .addClass('aj-hidden')
            .addClass('aj-notification')
            .append($('<p></p>').addClass('aj-message').text(message))
            .appendTo($('body'));
        this.addOverlay($popup);
        return $popup;
    },
    html: function (html) {
        var $popup = $('<div></div>')
            .addClass('aj-popup')
            .addClass('aj-hidden')
            .addClass('aj-html')
            .append($('<div></div>').html(html))
            .appendTo($('body'));
        this.addOverlay($popup);
        return $popup;
    },
    close: function () {
        $('.aj-popup').addClass('aj-hidden');
        $('#aj-overlay').remove();
    }
}

jQuery(function($) {
    AJPopup.init(); });
