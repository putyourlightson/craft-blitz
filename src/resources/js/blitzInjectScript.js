var Blitz = (function () {
    function Blitz() {
        var _this = this;
        this.processed = 0;
        var beforeBlitzInjectAll = document.createEvent('CustomEvent');
        beforeBlitzInjectAll.initCustomEvent('beforeBlitzInjectAll', false, true, null);
        if (!document.dispatchEvent(beforeBlitzInjectAll)) {
            return;
        }
        var urls = {};
        var elements = document.querySelectorAll('.blitz-inject:not(.blitz-inject--injected)');
        this.elementsToProcess = elements.length;
        elements.forEach(function (element) {
            var _a;
            var priority = element.getAttribute('data-blitz-priority');
            var url = {
                id: element.getAttribute('data-blitz-id'),
                uri: element.getAttribute('data-blitz-uri'),
                params: element.getAttribute('data-blitz-params'),
                response: '',
            };
            var beforeBlitzInject = document.createEvent('CustomEvent');
            beforeBlitzInject.initCustomEvent('beforeBlitzInject', false, true, url);
            if (!document.dispatchEvent(beforeBlitzInject)) {
                return;
            }
            var key = url.uri + (url.params && '?' + url.params);
            urls[key] = (_a = urls[key]) !== null && _a !== void 0 ? _a : [];
            urls[key].push(url);
        });
        var _loop_1 = function (key) {
            fetch(key)
                .then(function (response) {
                if (response.status >= 200 && response.status < 300) {
                    return response.text();
                }
            })
                .then(function (response) { return _this.replaceUrls(urls[key], response); });
        };
        for (var key in urls) {
            _loop_1(key);
        }
    }
    Blitz.prototype.replaceUrls = function (urls, response) {
        var _this = this;
        urls.forEach(function (url) {
            url.response = response;
            var element = document.getElementById('blitz-inject-' + url.id);
            if (element) {
                element.innerHTML = response;
                element.classList.add('blitz-inject--injected');
            }
            var afterBlitzInject = document.createEvent('CustomEvent');
            afterBlitzInject.initCustomEvent('afterBlitzInject', false, false, url);
            document.dispatchEvent(afterBlitzInject);
            _this.processed++;
        });
        if (this.processed >= this.elementsToProcess) {
            var afterBlitzInjectAll = document.createEvent('CustomEvent');
            afterBlitzInjectAll.initCustomEvent('afterBlitzInjectAll', false, false, null);
            document.dispatchEvent(afterBlitzInjectAll);
        }
    };
    return Blitz;
}());
;
document.addEventListener('{injectScriptEvent}', function () {
    new Blitz();
});
