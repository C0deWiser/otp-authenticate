(function () {
    'use strict';

    function getLocale() {
        var html = document.querySelector('html[lang]') || document.documentElement;
        var locale = html && html.getAttribute('lang');

        if (!locale && typeof navigator !== 'undefined') {
            locale = navigator.language;
        }

        return (locale || 'en').replace(/_/g, '-');
    }

    function createDurationFormatter(locale) {
        var units = [
            { seconds: 86400, name: 'day' },
            { seconds: 3600, name: 'hour' },
            { seconds: 60, name: 'minute' },
            { seconds: 1, name: 'second' }
        ];
        var formatters = {};

        if (typeof Intl !== 'undefined' && typeof Intl.NumberFormat === 'function') {
            try {
                for (var i = 0; i < units.length; i++) {
                    var formatter = new Intl.NumberFormat(locale, {
                        style: 'unit',
                        unit: units[i].name,
                        unitDisplay: 'long'
                    });

                    if (formatter.resolvedOptions().style !== 'unit') {
                        throw new Error('Intl unit formatting is not supported');
                    }

                    formatters[units[i].name] = formatter;
                }
            } catch (error) {
                formatters = {};
            }
        }

        function formatUnit(value, unit) {
            if (formatters[unit]) {
                return formatters[unit].format(value);
            }

            var names = {
                day: ['day', 'days'],
                hour: ['hour', 'hours'],
                minute: ['minute', 'minutes'],
                second: ['second', 'seconds']
            };

            return value + ' ' + names[unit][value === 1 ? 0 : 1];
        }

        return function (seconds) {
            var remaining = Math.max(0, Math.floor(seconds));
            var parts = [];

            for (var i = 0; i < units.length; i++) {
                var value = Math.floor(remaining / units[i].seconds);

                if (value > 0) {
                    parts.push(formatUnit(value, units[i].name));
                    remaining %= units[i].seconds;

                    if (parts.length === 2) {
                        break;
                    }
                }
            }

            if (!parts.length) {
                return formatUnit(0, 'second');
            }

            return parts.join(locale.split('-')[0].toLowerCase() === 'zh' ? '' : ' ');
        };
    }

    function startCountdown(countdown, formatDuration) {
        if (countdown.getAttribute('data-otp-countdown-initialized') === 'true') {
            return;
        }

        var form = countdown.closest('form');
        var button = form && form.querySelector('button[name="send"]');
        var template = countdown.getAttribute('data-otp-countdown-template');
        var seconds = parseInt(countdown.getAttribute('data-otp-countdown-seconds'), 10);

        if (!button || !template || isNaN(seconds) || seconds <= 0) {
            return;
        }

        countdown.setAttribute('data-otp-countdown-initialized', 'true');
        button.disabled = true;

        function update() {
            countdown.textContent = template.replace(/__otp_countdown_seconds__/g, formatDuration(seconds));

            if (seconds <= 0) {
                if (countdown.parentNode) {
                    countdown.parentNode.removeChild(countdown);
                }

                button.disabled = false;
                return;
            }

            seconds -= 1;
            window.setTimeout(update, 1000);
        }

        update();
    }

    var locale = getLocale();
    var formatDuration = createDurationFormatter(locale);
    var countdowns = document.querySelectorAll('[data-otp-countdown]');

    for (var i = 0; i < countdowns.length; i++) {
        startCountdown(countdowns[i], formatDuration);
    }
})();
