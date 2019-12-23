const blitzInjectData = [];

document.addEventListener('DOMContentLoaded', blitzInject);

async function blitzInject()
{
    if (blitzInjectData.length == 0) {
        return;
    }

    if (!document.dispatchEvent(new Event("beforeBlitzInjectAll", { cancelable: true }))) {
        return;
    }

    await Promise.all(
        blitzInjectData.map(async (data) => {
            const url = data.uri + (data.params && ("?" + data.params));
            const response = await fetch(url);
            const responseText = await response.text();
            const element = document.getElementById("blitz-inject-" + data.id);
            const blitzInjectEvent = {
                detail: {
                    uri: data.uri,
                    params: data.params,
                    element: element,
                    response: response,
                    responseText: responseText,
                },
                cancelable: true,
            };

            if (!document.dispatchEvent(new CustomEvent("beforeBlitzInject", blitzInjectEvent))) {
                return;
            }

            if (response.ok && element) {
                element.innerHTML = responseText;
            }

            document.dispatchEvent(new CustomEvent("afterBlitzInject", blitzInjectEvent));
        }));

    document.dispatchEvent(new Event("afterBlitzInjectAll"));
}
