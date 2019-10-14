function hideSidenav() {
    document.querySelector('#sidenav').setAttribute("class", "");       ///这样直接setAttr是为了兼容IE9…
    document.querySelector('.sidenav-overlay').setAttribute("class", "sidenav-overlay");
}

function showSidenav() {
    document.querySelector('#sidenav').setAttribute("class", "open");   ///这样直接setAttr是为了兼容IE9…
    document.querySelector('.sidenav-overlay').setAttribute("class", "sidenav-overlay active");
}


function judgeOptions() {
    
}


function annexTableMain(obj, maxCol, whichCol, beginRow, maxRow) {
    if (obj.children[0]) {

        var trs = obj.children[0].children;
        var tdText = "";
        var rowspanCount = 1;
        var extendingCellIndex = beginRow;
        var maxLength = Math.min(beginRow + maxRow, trs.length);
        for (var trIndex = beginRow; trIndex < maxLength; trIndex++) {
            if (whichCol >= trs[trIndex].children.length) {
                return;
            }
            if (trs[trIndex].children[whichCol].innerHTML !== tdText) {
                tdText = trs[trIndex].children[whichCol].innerHTML;
                if (rowspanCount !== 1) {
                    trs[extendingCellIndex].children[whichCol].rowSpan = rowspanCount;
                    if (whichCol < maxCol) annexTableMain(obj, maxCol, whichCol + 1, extendingCellIndex, rowspanCount);
                    rowspanCount = 1;
                }
                extendingCellIndex = trIndex;
            } else {
                trs[trIndex].children[whichCol].style.display = "none";
                rowspanCount++;
            }
        }
        if (rowspanCount !== 1) {
            trs[extendingCellIndex].children[whichCol].rowSpan = rowspanCount;
            if (whichCol < maxCol) annexTableMain(obj, maxCol, whichCol + 1, extendingCellIndex, rowspanCount);
        }
    }
}


function annexTableShell(classname, maxCol, whichCol, beginRow, maxRow) {
        if (!document.querySelector(classname)) return;
        whichCol = whichCol || 0;
        beginRow = beginRow || 0;
        maxRow   = maxRow || 65535;

        var objects = document.querySelectorAll(classname);
        for (var j=0; j<objects.length; j++) {
            annexTableMain(objects[j], maxCol, whichCol, beginRow, maxRow);
        }
}