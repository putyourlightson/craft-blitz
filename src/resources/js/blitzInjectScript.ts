interface Url {
  id: string;
  uri: string;
  params: string;
}

class Blitz {
    elementsToProcess: number;
    processed: number = 0;

    constructor() {
        if (!document.dispatchEvent(new CustomEvent('beforeBlitzInjectAll', {cancelable: true}))) {
            return;
        }

        const urls = {};
        const elements = document.querySelectorAll('.blitz-inject:not(.blitz-inject--injected)');
        this.elementsToProcess = elements.length;

        elements.forEach(element => {
            const priority = element.getAttribute('data-blitz-priority');
            const url: Url = {
                id: element.getAttribute('data-blitz-id'),
                uri: element.getAttribute('data-blitz-uri'),
                params: element.getAttribute('data-blitz-params'),
            };

            if (!document.dispatchEvent(new CustomEvent('beforeBlitzInject', {cancelable: true, detail: url}))) {
                return;
            }

            const key = url.uri + (url.params && '?' + url.params);
            urls[key] = urls[key] ?? [];
            urls[key].push(url);
        });

        for (const key in urls) {
            // Use fetch over XMLHttpRequest and register polyfills for IE11.
            fetch(key)
                .then(response => {
                    if (response.status >= 200 && response.status < 300) {
                        return response.text();
                    }
                })
                .then(response => this.replaceUrls(urls[key], response));
        }
    }

    replaceUrls(urls: Url[], response: string) {
        urls.forEach(url => {
            const element = document.getElementById('blitz-inject-' + url.id);
            if (element) {
                element.innerHTML = response;
                element.classList.add('blitz-inject--injected');
            }

            document.dispatchEvent(new CustomEvent('afterBlitzInject', {detail: url}));
            this.processed++;
        });

        if (this.processed >= this.elementsToProcess) {
            document.dispatchEvent(new CustomEvent('afterBlitzInjectAll'));
        }
    }
};

// The event name will be replaced with the `injectScriptEvent` config setting.
document.addEventListener('{injectScriptEvent}', () => {
    new Blitz();
});
