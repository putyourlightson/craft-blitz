var Blitz = {};

// The event name will be replaced with the `injectScriptEvent` config setting.
document.addEventListener('{injectScriptEvent}', blitzInject);

function blitzInject() {
    "use strict";

    Blitz = {
        inject: {
            data: document.querySelectorAll('.blitz-inject:not(.blitz-inject--injected)'),
            loaded: 0
        }
    };

    // Use IE compatible events (https://caniuse.com/#feat=customevent)
    var event = document.createEvent('CustomEvent');
    event.initCustomEvent('beforeBlitzInjectAll', false, true, null);

    if (!document.dispatchEvent(event)) {
        return;
    }

    Blitz.inject.data.forEach(function(data, index) {
        var id = data.getAttribute('data-blitz-id');
        var uri = data.getAttribute('data-blitz-uri');
        var params = data.getAttribute('data-blitz-params');

        var detail = {
            id: id,
            uri: uri,
            params: params
        };

        var event = document.createEvent('CustomEvent');
        event.initCustomEvent('beforeBlitzInject', false, true, detail);

        if (!document.dispatchEvent(event)) {
            return;
        }

        var xhr = new XMLHttpRequest();
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                var element = document.getElementById('blitz-inject-' + id);

                if (element) {
                    detail.element = element;
                    detail.responseText = this.responseText;

                    element.innerHTML = this.responseText;
                    element.classList.add('blitz-inject--injected');
                }

                var event = document.createEvent('CustomEvent');
                event.initCustomEvent('afterBlitzInject', false, false, detail);
                document.dispatchEvent(event);
            }

            Blitz.inject.loaded++;

            if (Blitz.inject.loaded >= Blitz.inject.data.length) {
                var event = document.createEvent('CustomEvent');
                event.initCustomEvent('afterBlitzInjectAll', false, false, null);
                document.dispatchEvent(event);
            }
        };

        xhr.open("GET", uri + (params && "?" + params));
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.send();
    });
}
