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

    // heightModify();
    // setTimeout(function () {
    //      heightModify();
    //      }, 2500);
}

function annexForm(whichcol) {
    let elementArray = document.getElementsByClassName(whichcol);
    let nowInnerHTML = elementArray[0].innerHTML;
    for (i=1, through=0, start=0; i < elementArray.length; i++) {
        if ((elementArray[i].innerHTML !== nowInnerHTML) || (elementArray[i].innerHTML === "")) {
            elementArray[start].rowSpan = through+1;
            for (k=start+1; k<i; k++) {
                elementArray[k].style.display = "none";
            }
            start = i;
            through = 0;
            nowInnerHTML = elementArray[i].innerHTML;
        }
        else {
            through++;
        }
    }
    elementArray[start].rowSpan = through+1;
    for (k=start+1; k<i; k++) {
        elementArray[k].style.display = "none";
    }
}

function editPron(originalData) {
    orininalPron = originalData[0] + ' ' + originalData[1] + ' ' + originalData[2] + ' ' + originalData[3] + ' ' + originalData[6];
    let prom = prompt("1月16: 現在不要改先！按“聲母 韻腹 韻尾 聲調 IPA”順序輸入（無引號，有英文空格）", orininalPron);
    if (prom!==null) {
        let newPron = prom.split(" ");
        let abReg   = /^[a-z]{0,6}$/;
        let dgReg   = /^\d\d?$/;
        let ipaReg  = /^.{1,6}\d{1,3}$/;
        //暫時衹在這裏檢測合法性吧…
        //IPA怎麼檢測我還真不知道（
        if ((newPron.length === 5) && (ipaReg.test(newPron[4])) && (dgReg.test(newPron[3])) && (abReg.test(newPron[2])) && (abReg.test(newPron[1])) && (abReg.test(newPron[0]))) {
            let xmlhttp = new XMLHttpRequest();
            let newData = "ty=1&on="+newPron[0]+"&nu="+newPron[1]+"&co="+newPron[2]+"&to="+newPron[3]+"&cs="+originalData[4]+"&id="+originalData[5]+"&ip="+newPron[4];
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState==4 && xmlhttp.status==200) {
                    alert(newData);
                    location.reload();
                }
            };
            xmlhttp.open("GET","fun/modifyCharaSheet.php?"+newData,true);
            xmlhttp.send();

        } else {
            alert(newPron + "不合條件!");
        }
    }
}

function editPronNote(originalData) {
    orininalNote = originalData[2];
    let prom = prompt("輸入備註", orininalNote);
    if (prom!==null) {
        //改備註用的，暫時放著
        if ((newPron.length === 5) && (ipaReg.test(newPron[4])) && (dgReg.test(newPron[3])) && (abReg.test(newPron[2])) && (abReg.test(newPron[1])) && (abReg.test(newPron[0]))) {
            let xmlhttp = new XMLHttpRequest();
            let newData = "ty=1&on="+newPron[0]+"&nu="+newPron[1]+"&co="+newPron[2]+"&to="+newPron[3]+"&cs="+originalData[4]+"&id="+originalData[5]+"&ip="+newPron[4];
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState==4 && xmlhttp.status==200) {
                    alert(newData);
                    location.reload();
                }
            };
            xmlhttp.open("GET","fun/modifyCharaSheet.php?"+newData,true);
            xmlhttp.send();

        } else {
            alert(newPron + "不合條件!");
        }
    }
}