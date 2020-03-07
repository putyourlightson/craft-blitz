// The event will be replaced with the `injectScriptEvent` config setting.
document.addEventListener("{injectScriptEvent}", blitzInject);

function blitzInject() {
    "use strict";

    const Blitz = {
        inject: {
            data: document.querySelectorAll('.blitz-inject:not(.blitz-inject--injected)'),
            loaded: 0
        }
    };

    const event = new CustomEvent("beforeBlitzInjectAll", {
        cancelable: true
    });

    if (!document.dispatchEvent(event)) {
        return;
    }

    Blitz.inject.data.forEach(function(data, index) {
        const dataUri = data.getAttribute('data-blitz-uri');
        const dataParams = data.getAttribute('data-blitz-params')
        const dataId = data.getAttribute('data-blitz-id')

        const customEventInit = {
            detail: {
                uri: dataUri,
                params: dataParams
            },
            cancelable: true
        };

        if (!document.dispatchEvent(new CustomEvent("beforeBlitzInject", customEventInit))) {
            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                const element = document.getElementById("blitz-inject-" + dataId);

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

        xhr.open("GET", dataUri + (dataParams && "?" + dataParams));
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.send();
    });
}
