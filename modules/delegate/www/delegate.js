var internalBackends = [];
var internalBackendsLength = 0;
var loginTexts = [];
var loginTextsLength = 0;
var infoTexts = [];
var infoTextsLength = 0;

window.addEventListener('load', function() {
    var backendList = document.getElementById('backend-selectors');

    // If backendList is null, it means this SP has only one internal backend,
    // and radio buttons for selecting backend is not generated.
    if (backendList !== null) {
        internalBackends = getElementsByClass('backend-selector', backendList, 'input');
        internalBackendsLength = internalBackends.length;
        for (var i = 0; i < internalBackendsLength; i++) {
            internalBackends[i].onclick = changeBackend;
        }
    }

    // Check if there is any login or info text that should be displayed.
    // This still needs to be done even if there is only one internal backend.
    var loginTextsList = document.getElementById('login-text-wrapper');
    loginTexts = getElementsByClass('login-text', loginTextsList, 'div');
    loginTextsLength = loginTexts.length;

    var activeLoginText = getElementsByClass('active', loginTextsList, 'div');
    if (activeLoginText.length > 0) {
        elementAddClass(loginTextsList, 'active');
    }

    var infoTextsList = document.getElementById('info-text-wrapper');
    infoTexts = getElementsByClass('info-text', infoTextsList, 'div');
    infoTextsLength = infoTexts.length;
    var activeInfoText = getElementsByClass('active', infoTextsList, 'div');
    if (activeInfoText.length > 0) {
        elementAddClass(infoTextsList, 'active');
    }
});

function changeBackend(e) {
    updateLoginText(this);
    updateInfoText(this);
}

function updateLoginText(backend) {
    for (var i = 0; i < loginTextsLength; i++) {
        elementRemoveClass(loginTexts[i], 'active');
    }
    var currentLoginTextId = backend.id.replace('backend-selector', 'login-text');
    var currentLoginText = document.getElementById(currentLoginTextId);

    var loginTextWrapper = document.getElementById('login-text-wrapper');
    if (currentLoginText !== null) {
        elementAddClass(currentLoginText, 'active');
        elementAddClass(loginTextWrapper, 'active');
    }
    else {
        elementRemoveClass(loginTextWrapper, 'active');
    }
}

function updateInfoText(backend) {
    for (var i = 0; i < infoTextsLength; i++) {
        elementRemoveClass(infoTexts[i], 'active');
    }
    var currentInfoTextId = backend.id.replace('backend-selector', 'info-text');
    var currentInfoText = document.getElementById(currentInfoTextId);

    var infoTextWrapper = document.getElementById('info-text-wrapper');
    if (currentInfoText !== null) {
        elementAddClass(currentInfoText, 'active');
        elementAddClass(infoTextWrapper, 'active');
    }
    else {
        elementRemoveClass(infoTextWrapper, 'active');
    }
}

/* http://www.dustindiaz.com/getelementsbyclass/ <=
   http://ejohn.org/blog/getelementsbyclassname-speed-comparison/ */

function getElementsByClass(searchClass,node,tag) {
    if(navigator.appName.indexOf("Internet Explorer") != -1) {
        var classElements = new Array();
        if ( node == null )
            node = document;
        if ( tag == null )
            tag = '*';
        var els = node.getElementsByTagName(tag);
        var elsLen = els.length;
        for (i = 0; i < elsLen; i++) {
            if ( elementHasClass(els[i], searchClass) ) {
                classElements.push(els[i]);
            }
        }
        return classElements;
    } else {
        return node.getElementsByClassName(searchClass);
    }
}

/* http://snipplr.com/view/3561/addclass-removeclass-hasclass/ */

function elementHasClass(ele,cls) {
    return ele.className.match(new RegExp('(\\s|^)'+cls+'(\\s|$)'));
}

function elementAddClass(ele,cls) {
    if (!this.elementHasClass(ele,cls)) ele.className += " "+cls;
}

function elementRemoveClass(ele,cls) {
    if (elementHasClass(ele,cls)) {
        var reg = new RegExp('(\\s|^)'+cls+'(\\s|$)');
        ele.className=ele.className.replace(reg,' ');
    }
}
