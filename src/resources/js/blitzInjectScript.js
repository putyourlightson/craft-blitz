function blitzInject(id, uri, params) {
    const customEventInit = {
        detail: {
            uri: uri,
            params: params,
        },
        cancelable: true,
    };

    const xhr = new XMLHttpRequest();
    xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 300) {
            const element = document.getElementById("blitz-inject-" + id);
            if (element) {
                customEventInit.detail.element = element;
                customEventInit.detail.responseText = this.responseText;

                if (!document.dispatchEvent(new CustomEvent("beforeBlitzInject", customEventInit))) {
                    return;
                }

                element.innerHTML = this.responseText;

                document.dispatchEvent(new CustomEvent("afterBlitzInject", customEventInit));
            }
        }
    };
    xhr.open("GET", uri + (params && ("?" + params)));
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.send();
}
