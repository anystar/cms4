var b = document.getElementById('closeflyout');

b.onclick = function (evt) {
	window.parent.document.flyoutToggle.trigger("click");
}

// var c = document.getElementById('expandflyout');

// c.onclick = function (evt) {
// 	window.parent.document.$flyout.toggleClass("expanded");
// }


function goBack() {
    window.history.back();
}