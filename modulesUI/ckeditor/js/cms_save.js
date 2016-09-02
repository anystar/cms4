CKEDITOR.plugins.add( 'cmssave',
{
	init: function( editor )
	{
		var config = editor.config.inlinesave,
		    iconName;

		var postUrl = "{{@BASE}}/admin/content/save";

		if (typeof config == "undefined") { // Give useful error message if user doesn't define config.inlinesave
			config = {}; // default to empty object
		}

		iconName = 'inlinesave-color.svg';

		editor.addCommand( 'inlinesave',
			{
				exec : function( editor )
				{
					var postData = {},
					    payload = '',
					    contentType = 'application/x-www-form-urlencoded; charset=UTF-8';

					if (typeof config.onSave == "function") {
						var sendDataOk = config.onSave(editor); // Allow showing 'loading' spinner or aborting

						if (typeof sendDataOk != "undefined" && !sendDataOk) {  // Explicit return false?
							if (typeof config.onFailure == "function") {
								config.onFailure(editor, -1, null);  	// -1 means "Save aborted"
							}
							else {
								throw new Error("CKEditor inlinesave: Saving Disable by return of onSave function = false");
							}
							return;
						}

					}

					// Clone postData object from config and add editabledata and editorID properties
					CKEDITOR.tools.extend(postData, config.postData || {}, true); // Clone config.postData to prevent changing the config.
					postData.editabledata = editor.getData();
					postData.editorID = editor.container.getId();
					postData.page = config.page;
					postData.contentBlock = config.contentBlock;

					// Convert postData object to multi-part form data query string for post like jQuery does by default.
					var formData = '';
					for (var key in postData) { // Must encode data to handle special characters
							formData += '&' + key + '=' + encodeURIComponent(postData[key]);
					}
					payload = formData.slice(1); // Remove initial '&'

					// Use pure javascript (no dependencies) and send the data in json format...
					var xhttp = new XMLHttpRequest();
					xhttp.onreadystatechange = function () {
						if (xhttp.readyState == 4) {
							// If success, call onSuccess callback if defined
							if (typeof config.onSuccess == "function" && xhttp.status == 200) {
								// Allow server to return data via xhttp.response
								config.onSuccess(editor, xhttp.response);
							}
							// If error, call onFailure callback if defined
							else if (typeof config.onFailure == "function") {
								config.onFailure(editor, xhttp.status, xhttp);
							}
						}
					};
					xhttp.open("POST", postUrl, true);
					// Send as form data encoded to handle special characters.
					xhttp.setRequestHeader("Content-type", contentType);
					xhttp.send(payload);
				}
			});
		editor.ui.addButton( 'Inlinesave',
		{
			toolbar: 'document',
			label: 'Save',
			command: 'inlinesave',
			icon: this.path + 'images/' + iconName
		} );
	}
} );
