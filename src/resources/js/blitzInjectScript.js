var Blitz = {};

// The event name will be replaced with the `injectScriptEvent` config setting.
document.addEventListener('{injectScriptEvent}', blitzInject);

function blitzInject() {
    'use strict';

    Blitz = {
        inject: {
            data: document.querySelectorAll('.blitz-inject:not(.blitz-inject--injected)'),
            loaded: 0
        }
    };

    // Use IE compatible events (https://caniuse.com/#feat=customevent)
    var beforeBlitzInjectAll = document.createEvent('CustomEvent');
    beforeBlitzInjectAll.initCustomEvent('beforeBlitzInjectAll', false, true, null);

    if (!document.dispatchEvent(beforeBlitzInjectAll)) {
        return;
    }

    // Use IE compatible for loop
    for (var i = 0; i < Blitz.inject.data.length; i++) {
        var data = Blitz.inject.data[i];

        var values = {
            id: data.getAttribute('data-blitz-id'),
            uri: data.getAttribute('data-blitz-uri'),
            params: data.getAttribute('data-blitz-params')
        };

        var beforeBlitzInject = document.createEvent('CustomEvent');
        beforeBlitzInject.initCustomEvent('beforeBlitzInject', false, true, values);

        if (!document.dispatchEvent(beforeBlitzInject)) {
            return;
        }

        blitzReplace(values);
    }
}

function blitzReplace(values) {
    'use strict';

    var xhr = new XMLHttpRequest();
    xhr.onload = function() {
        if (this.status >= 200 && this.status < 300) {
            var element = document.getElementById('blitz-inject-' + values.id);

            if (element) {
                values.element = element;
                values.responseText = this.responseText;

                element.innerHTML = this.responseText;
                element.classList.add('blitz-inject--injected');
            }

            var afterBlitzInject = document.createEvent('CustomEvent');
            afterBlitzInject.initCustomEvent('afterBlitzInject', false, false, values);
            document.dispatchEvent(afterBlitzInject);
        }

        Blitz.inject.loaded++;

        if (Blitz.inject.loaded >= Blitz.inject.data.length) {
            var afterBlitzInjectAll = document.createEvent('CustomEvent');
            afterBlitzInjectAll.initCustomEvent('afterBlitzInjectAll', false, false, null);
            document.dispatchEvent(afterBlitzInjectAll);
        }
    };

    xhr.open("GET", values.uri + (values.params && "?" + values.params));
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.send();
}
