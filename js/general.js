function hideSidenav() {
    document.querySelector('#sidenav').setAttribute("class", "");       ///这样直接setAttr是为了兼容IE9…
    document.querySelector('.sidenav-overlay').setAttribute("class", "sidenav-overlay");
}

function showSidenav() {
    document.querySelector('#sidenav').setAttribute("class", "open");   ///这样直接setAttr是为了兼容IE9…
    document.querySelector('.sidenav-overlay').setAttribute("class", "sidenav-overlay active");
}

function annexForm(name, maxCol,whichCol, begin, max) {
    whichCol = whichCol || 0;
    begin    = begin || 0;
    max      = max || 65535;
    let trs  = document.querySelector(name).children[0].children;
    let tdText = "";
    let rowspancount = 1;
    let extendingCellIndex = begin;
    let maxLength = Math.min(begin+max, trs.length);
    for (let trIndex = begin; trIndex<maxLength;trIndex++) {
        if (whichCol>=trs[trIndex].children.length) {
            return;
        } else if (trs[trIndex].children[whichCol].innerHTML!==tdText) {
            tdText = trs[trIndex].children[whichCol].innerHTML;
            trs[extendingCellIndex].children[whichCol].rowSpan = rowspancount;
            if (whichCol<maxCol) annexForm(name, maxCol, whichCol+1,extendingCellIndex,rowspancount);
            rowspancount = 1;
            extendingCellIndex = trIndex;
        } else {
            trs[trIndex].children[whichCol].style.display = "none";
            rowspancount++;
        }
    }
    trs[extendingCellIndex].children[whichCol].rowSpan = rowspancount;
    if (whichCol<maxCol) annexForm(name, maxCol, whichCol+1,extendingCellIndex,rowspancount);
}