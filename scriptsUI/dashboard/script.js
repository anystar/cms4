var b = document.getElementById('closeflyout');

b.onclick = function (evt) {
	window.parent.document.flyoutToggle.trigger("click");
}

function goBack() {
    window.history.back();
}