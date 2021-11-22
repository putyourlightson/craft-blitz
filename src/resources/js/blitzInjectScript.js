var Blitz = (function () {
    function Blitz() {
        var _this = this;
        this.processed = 0;
        if (!document.dispatchEvent(new CustomEvent('beforeBlitzInjectAll', { cancelable: true }))) {
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
            };
            if (!document.dispatchEvent(new CustomEvent('beforeBlitzInject', { cancelable: true, detail: url }))) {
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
                .then(function (response) {
                _this.replaceUrls(urls[key], response);
            });
        };
        for (var key in urls) {
            _loop_1(key);
        }
    }
    Blitz.prototype.replaceUrls = function (urls, response) {
        var _this = this;
        urls.forEach(function (url) {
            var element = document.getElementById('blitz-inject-' + url.id);
            if (element) {
                element.innerHTML = response;
                element.classList.add('blitz-inject--injected');
            }
            document.dispatchEvent(new CustomEvent('afterBlitzInject', { detail: url }));
            _this.processed++;
        });
        if (this.processed >= this.elementsToProcess) {
            document.dispatchEvent(new CustomEvent('afterBlitzInjectAll'));
        }
    };
    return Blitz;
}());
;
document.addEventListener('{injectScriptEvent}', function () {
    new Blitz();
});
