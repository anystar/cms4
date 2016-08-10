$(function() {

	var sg_div = $('<div>').attr('id', 'webworkscms_admintoolbar');
	$('body').append(sg_div);

	var save = $('<input id="savebutton" type="button" value="Save"/>');
	save.click(function () {

		Object.keys(CKEDITOR.instances).forEach(function(key,index) {
			
			var editor = CKEDITOR.instances[key];
			var postData = {};

			postData.editabledata = editor.getData();
			postData.editorID = editor.container.getId();

			payload = JSON.stringify(postData);

			var formData = '';
			
			for (var key in postData) { // Must encode data to handle special characters
					formData += '&' + key + '=' + encodeURIComponent(postData[key]);
			}

			payload = formData.slice(1); // Remove initial '&'
			
			// Use pure javascript (no dependencies) and send the data in json format...
			var xhttp = new XMLHttpRequest();

		    xhttp.onreadystatechange = function() {
		        if (xhttp.readyState == 1) { 
					$("#savebutton").val("saving...");
					
					setTimeout(
					  function() 
					  {
					    $("#savebutton").val("Saved!").css("background", "#3cab4e");

						setTimeout(
						  function() { $("#savebutton").val("Save"); }, 1500);
					  	}, 2000);
		        }
		    };

			xhttp.open("POST", "{{@BASE}}/admin/page/save", true);
			// Send as form data encoded to handle special characters.
			xhttp.setRequestHeader("Content-type", 'application/x-www-form-urlencoded');
			xhttp.send(payload);
		});

		return true;
	});

	sg_div.append(save);

	<check if="{{@webmaster}}">
	sg_div.append($('<a href="{{@BASE}}/admindb" target="_cmswindow" type="button">DB</a>'));
	</check>
	sg_div.append($('<a href="{{@BASE}}/admin" target="_cmswindow" type="button">CMS Panel</a>'));

	$('body').css({
		'transition': 'transform 0.4s ease'
	});

});