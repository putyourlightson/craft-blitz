var Blitz = {
    inject: {
        data: [],
        loaded: 0
    }
};

document.addEventListener("DOMContentLoaded", blitzInject);

function blitzInject() {
    "use strict";

    var event = new Event("beforeBlitzInjectAll", {
        cancelable: true
    });

    if (!document.dispatchEvent(event)) {
        return;
    }

    Blitz.inject.data.forEach(function(data, index) {
        var customEventInit = {
            detail: {
                uri: data.uri,
                params: data.params
            },
            cancelable: true
        };

        if (!document.dispatchEvent(new CustomEvent("beforeBlitzInject", customEventInit))) {
            return;
        }

        var xhr = new XMLHttpRequest();

        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                var element = document.getElementById("blitz-inject-" + data.id);

                if (element) {
                    customEventInit.detail.element = element;
                    customEventInit.detail.responseText = this.responseText;

                    element.innerHTML = this.responseText;
                }

                document.dispatchEvent(new CustomEvent("afterBlitzInject", customEventInit));
            }

            Blitz.inject.loaded++;

            if (Blitz.inject.loaded >= Blitz.inject.data.length) {
                document.dispatchEvent(new Event("afterBlitzInjectAll"));
            }
        };

        xhr.open("GET", data.uri + (data.params && "?" + data.params));
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.send();
    });
}
