var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
const injectScriptEvent = '{injectScriptEvent}';
if (injectScriptEvent === 'load') {
    window.addEventListener('load', injectElements, { once: true });
}
else {
    document.addEventListener(injectScriptEvent, injectElements, { once: true });
}
function injectElements() {
    return __awaiter(this, void 0, void 0, function* () {
        if (!document.dispatchEvent(new CustomEvent('beforeBlitzInjectAll', {
            cancelable: true,
        }))) {
            return;
        }
        const elements = document.querySelectorAll('.blitz-inject:not(.blitz-inject--injected)');
        const injectElements = {};
        const promises = [];
        elements.forEach(element => {
            var _a;
            const injectElement = {
                element: element,
                id: element.getAttribute('data-blitz-id'),
                uri: element.getAttribute('data-blitz-uri'),
                params: element.getAttribute('data-blitz-params'),
                property: element.getAttribute('data-blitz-property'),
            };
            if (document.dispatchEvent(new CustomEvent('beforeBlitzInject', {
                cancelable: true,
                detail: injectElement,
            }))) {
                const url = injectElement.uri + (injectElement.params ? (injectElement.uri.indexOf('?') !== -1 ? '&' : '?') + injectElement.params : '');
                injectElements[url] = (_a = injectElements[url]) !== null && _a !== void 0 ? _a : [];
                injectElements[url].push(injectElement);
            }
        });
        for (const url in injectElements) {
            promises.push(replaceUrls(url, injectElements[url]));
        }
        yield Promise.all(promises);
        document.dispatchEvent(new CustomEvent('afterBlitzInjectAll'));
    });
}
function replaceUrls(url, injectElements) {
    return __awaiter(this, void 0, void 0, function* () {
        const response = yield fetch(url);
        if (response.status >= 300) {
            return null;
        }
        const responseText = yield response.text();
        let responseJson;
        if (url.indexOf('blitz/csrf/json') !== -1) {
            responseJson = JSON.parse(responseText);
        }
        injectElements.forEach(injectElement => {
            var _a;
            if (injectElement.property) {
                injectElement.element.innerHTML = (_a = responseJson[injectElement.property]) !== null && _a !== void 0 ? _a : '';
            }
            else {
                injectElement.element.innerHTML = responseText;
            }
            injectElement.element.classList.add('blitz-inject--injected');
            document.dispatchEvent(new CustomEvent('afterBlitzInject', {
                detail: injectElement,
            }));
        });
    });
}
