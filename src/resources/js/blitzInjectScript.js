var Blitz = {};

// The event name will be replaced with the `injectScriptEvent` config setting.
document.addEventListener("{injectScriptEvent}", blitzInject);

function blitzInject() {
    "use strict";

    Blitz = {
        inject: {
            data: document.querySelectorAll('.blitz-inject:not(.blitz-inject--injected)'),
            loaded: 0
        }
    };

    var event = new CustomEvent("beforeBlitzInjectAll", {
        cancelable: true
    });

    if (!document.dispatchEvent(event)) {
        return;
    }

    Blitz.inject.data.forEach(function(data, index) {
        var id = data.getAttribute('data-blitz-id');
        var uri = data.getAttribute('data-blitz-uri');
        var params = data.getAttribute('data-blitz-params');

        var customEventInit = {
            detail: {
                id: id,
                uri: uri,
                params: params
            },
            cancelable: true
        };

        if (!document.dispatchEvent(new CustomEvent("beforeBlitzInject", customEventInit))) {
            return;
        }

        var xhr = new XMLHttpRequest();
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                var element = document.getElementById("blitz-inject-" + id);

                if (element) {
                    customEventInit.detail.element = element;
                    customEventInit.detail.responseText = this.responseText;

                    element.innerHTML = this.responseText;
                    element.classList.add('blitz-inject--injected');
                }

                document.dispatchEvent(new CustomEvent("afterBlitzInject", customEventInit));
            }

            Blitz.inject.loaded++;

            if (Blitz.inject.loaded >= Blitz.inject.data.length) {
                document.dispatchEvent(new CustomEvent("afterBlitzInjectAll"));
            }
        };

        xhr.open("GET", uri + (params && "?" + params));
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.send();
    });
}
