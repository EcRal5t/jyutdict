// function addNode() {
//     let div = document.getElementById("divid");
//     let node = document.getElementById("pid");
//     let newnode = document.createElement("p");
//     newnode.innerHTML = "新加的";
//     div.insertBefore(newnode,node);
//
// }
//
// function removeNode() {
//     let div = document.getElementById("divid");
//     let p = div.removeChild(div.childNodes[1]);
//
// }
//
//
// var setMove;
// function move(objStr, stop) {
//     let obj = document.getElementById(objStr);
//
//     obj.style.marginLeft = ((isNaN(parseFloat(obj.style.marginLeft))?0:parseFloat(obj.style.marginLeft)+0.5 + "px"));
//     setMove = setTimeout(function() {move(objStr)}, 5);
//     // alert(parseInt(obj.style.marginLeft)*1.5+2);
// }
//
//
// function stopMove() {
//     clearTimeout(setMove);
// }
//
//
// function heightModify() {
//     document.body.style.height = window.innerHeight + "px";
//     let heightModifyingFunc = setTimeout(function () {
//         heightModify();
//     }, 1500);
// }

function heightModify() {
    document.body.style.height = window.innerHeight + "px";
    //let heightModifyingFunc = setTimeout(function () { heightModify(); }, 1500);
}

function tabHighlightRectanglePos(which) {
    let rectangle = document.getElementById("highlightRectangle");
    let navBar = document.getElementById("leftNavBar");
    rectangle.style.left = navBar.style.width*which + "px";
}


function initial(which) {
    //tabHighlightRectanglePos(which);
    setTimeout(function () {
         heightModify();
         }, 2500);
}



