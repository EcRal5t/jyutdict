function hideLeftNavBar() {
    let navBar = document.getElementById("leftNavBar");
    let highlightRec = document.getElementById("highlightRectangle");
    navBar.style.flex = 0;
    highlightRec.style.display = "none";
}
function showLeftNavBar() {
    let navBar = document.getElementById("leftNavBar");
    let highlightRec = document.getElementById("highlightRectangle");
    navBar.style.flex = 1;
    highlightRec.style.display = "block";
}

function toggleLeftNavBar() {
    let navBar = document.getElementById("leftNavBar");
    let highlightRec = document.getElementById("highlightRectangle");
    //alert(highlightRec.style.display);

    if (highlightRec.style.display === "block" || highlightRec.style.display === "") {
        navBar.style.flex = "0";
        highlightRec.style.display = "none";

    } else {
        navBar.style.flex = "1";
        highlightRec.style.display = "block";
    }
}

function judgeHiddenNavBar() {
    let width = window.innerWidth;
    if (width < 630) {
        toggleLeftNavBar();
    }
}