function hideLeftNavBar() {
    let navBar = document.getElementById("leftNavBar");
    let highlightRec = document.getElementById("highlightRectangle");
    navBar.style.display = 0;
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
    let bottom = document.getElementsByClassName("bottom");
    //alert(highlightRec.style.display);

    if (highlightRec.style.display === "block" || highlightRec.style.display === "") {
        navBar.style.display = "none";
        highlightRec.style.display = "none";
        bottom[0].style.display = "none";
        bottom[1].style.display = "none";
    } else {
        navBar.style.display = "inline";
        highlightRec.style.display = "block";
        bottom[0].style.display = "inherit";
        bottom[1].style.display = "inherit";
    }
}

function judgeHiddenNavBar() {
    if (window.innerWidth < 630) {
        toggleLeftNavBar();
    }
}

