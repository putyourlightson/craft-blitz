interface Url {
  id: string;
  uri: string;
  params: string;
  response: string;
}

class Blitz {
    elementsToProcess: number;
    processed: number = 0;

    constructor() {
        // Use IE compatible events (https://caniuse.com/#feat=customevent)
        const beforeBlitzInjectAll = document.createEvent('CustomEvent');
        beforeBlitzInjectAll.initCustomEvent('beforeBlitzInjectAll', false, true, null);

        if (!document.dispatchEvent(beforeBlitzInjectAll)) {
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
                response: '',
            };

            // Use IE compatible events (https://caniuse.com/#feat=customevent)
            const beforeBlitzInject = document.createEvent('CustomEvent');
            beforeBlitzInject.initCustomEvent('beforeBlitzInject', false, true, url);

            if (!document.dispatchEvent(beforeBlitzInject)) {
                return;
            }

            const key = url.uri + (url.params && '?' + url.params);
            urls[key] = urls[key] ?? [];
            urls[key].push(url);
        });

        for (const key in urls) {
            // Use Fetch over XMLHttpRequest (clunky) and register polyfills for IE11.
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
            url.response = response;

            const element = document.getElementById('blitz-inject-' + url.id);
            if (element) {
                element.innerHTML = response;
                element.classList.add('blitz-inject--injected');
            }

            // Use IE compatible events (https://caniuse.com/#feat=customevent)
            var afterBlitzInject = document.createEvent('CustomEvent');
            afterBlitzInject.initCustomEvent('afterBlitzInject', false, false, url);
            document.dispatchEvent(afterBlitzInject);

            this.processed++;
        });

        if (this.processed >= this.elementsToProcess) {
            // Use IE compatible events (https://caniuse.com/#feat=customevent)
            var afterBlitzInjectAll = document.createEvent('CustomEvent');
            afterBlitzInjectAll.initCustomEvent('afterBlitzInjectAll', false, false, null);
            document.dispatchEvent(afterBlitzInjectAll);
        }
    }
};

// The event name will be replaced with the `injectScriptEvent` config setting.
document.addEventListener('{injectScriptEvent}', () => {
    new Blitz();
});
