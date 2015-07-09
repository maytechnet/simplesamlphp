/*
 *
 * JavaScript functions for the Delegate-flaps theme.
 *
 */

var flapsLength = 0;
var flaps = [];

window.addEventListener('load', function() {
    flaps = getElementsByClass("internal-backend", document, "a");
    flapsLength = flaps.length; //performance

    // If flapsLength is 1 (or less, although not likely), it means this SP has
    // only one internal backend configured, which means there is no need to
    // add event handlers.
    if (flapsLength > 1) {
        for(var i = 0; i < flapsLength; i++) {
            flaps[i].onclick = appDelegateChangeService;
        }
    }
});

function appDelegateChangeService(e) {
    var backendId = this.id.replace("internal-backend", "backend-selector");
    var backendRadioButton = document.getElementById(backendId);
    backendRadioButton.checked = true;

    // Update login and info texts by function in delegate.js
    updateLoginText(backendRadioButton);
    updateInfoText(backendRadioButton);

    for (var i = 0; i < flapsLength; i++) {
        elementRemoveClass(flaps[i], "active");
    }
    elementAddClass(this, "active");
}
