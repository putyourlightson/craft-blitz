// The event name will be replaced with the `injectScriptEvent` config setting.
document.addEventListener('{injectScriptEvent}', injectElements);

interface InjectElement {
    element: Element;
    id: string;
    uri: string;
    params: string;
    property: string;
}

async function injectElements() {
    const shouldInjectAll: boolean = document.dispatchEvent(
        new CustomEvent('beforeBlitzInjectAll', {
            cancelable: true,
        })
    );

    if (shouldInjectAll === false) {
        return;
    }

    const elements = document.querySelectorAll('.blitz-inject:not(.blitz-inject--injected)');
    const injectElements = {};
    const promises = [];

    elements.forEach(element => {
        const injectElement: InjectElement = {
            element: element,
            id: element.getAttribute('data-blitz-id'),
            uri: element.getAttribute('data-blitz-uri'),
            params: element.getAttribute('data-blitz-params'),
            property: element.getAttribute('data-blitz-property'),
        };


        const shouldInject: boolean = document.dispatchEvent(
            new CustomEvent('beforeBlitzInject', {
                cancelable: true,
                detail: injectElement,
            })
        );

        if (shouldInject === true) {
            const url = injectElement.uri + (injectElement.params && '?' + injectElement.params);
            injectElements[url] = injectElements[url] ?? [];
            injectElements[url].push(injectElement);
        }
    });

    for (const url in injectElements) {
        promises.push(replaceUrls(url, injectElements[url]));
    }

    await Promise.all(promises);

    document.dispatchEvent(
        new CustomEvent('afterBlitzInjectAll')
    );
}

async function replaceUrls(url: string, injectElements: InjectElement[]) {
    const response = await fetch(url);

    if (response.status >= 300) {
        return null;
    }

    const responseText = await response.text();
    let responseJson;

    if (url.indexOf('blitz/csrf/json') !== -1) {
        responseJson = JSON.parse(responseText);
    }

    injectElements.forEach(injectElement => {
        if (injectElement.property) {
            injectElement.element.innerHTML = responseJson[injectElement.property] ?? '';
        } else {
            injectElement.element.innerHTML = responseText;
        }

        injectElement.element.classList.add('blitz-inject--injected');

        document.dispatchEvent(
            new CustomEvent('afterBlitzInject', {
                detail: injectElement,
            })
        );
    });
}
