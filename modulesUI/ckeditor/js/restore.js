CKEDITOR.plugins.add( 'restore',
{
	init: function( editor )
	{
		editor.addCommand( 'restoreCommand', {
			exec : function (editor) {

				var dialogID = makeid();

// Start dialog definition
		CKEDITOR.dialog.add( dialogID, function( editor )
		{
			var xmlHttp = new XMLHttpRequest();
			xmlHttp.open( "GET", "{{@BASE}}/admin/ckeditor/getRevisions/"+editor.name, false ); // false for synchronous request
			xmlHttp.send( null );

			if (xmlHttp.responseText == "")
			{
				return {
					title : 'Restore previous versions',
					minWidth : 200,
					minHeight : 200,
					contents :
					[
						{
							id : 'general',
							label : 'Settings',
							elements : [
								{
							 		type: 'html',
							 		html: "No history for this content block"
							 	}
							]
						}
					]
				};
			}

			var ckeditor_revisions = JSON.parse(xmlHttp.responseText);

			var buttons = [];
			for (var i = 0; i < ckeditor_revisions.length; i++) {

				    buttons.push(
						{
							type : "hbox",
							widths : ['40%', '60%'],
							children : [					
								{
							 		type: 'html',
							 		html: ckeditor_revisions[i].date
							 	},
							 	{
							 		type: 'button',
							 		id: 'textfield',
							 		label: 'Restore',
							 		style : 'float:right;position:relative;top:-3px',
									revision_id : ckeditor_revisions[i].id,
							 		onLoad: function (a) {

							 			CKEDITOR.document.getById(this.domId).on("click", function (e) {
							 				// ajax get this and dump it into editor. -> this.revision_id
											// Use pure javascript (no dependencies) and send the data in json format...
											var xhttp = new XMLHttpRequest();
											xhttp.onreadystatechange = function () {
												if (this.readyState == 4 && this.status == 200) {
													
													editor.setData(this.response);

									 				CKEDITOR.dialog.getCurrent().hide()
												}
											};

											var postData = {};
											postData.id = this.revision_id;				
											// Convert postData object to multi-part form data query string for post like jQuery does by default.
											var formData = '';
											for (var key in postData) { // Must encode data to handle special characters
													formData += '&' + key + '=' + encodeURIComponent(postData[key]);
											}
											payload = formData.slice(1); // Remove initial '&'

											xhttp.open("POST", getRevisionURL, true);
											// Send as form data encoded to handle special characters.
											xhttp.setRequestHeader("Content-type", 'application/x-www-form-urlencoded; charset=UTF-8');
											xhttp.send(payload);

							 			}, this);
							 		}
							 	}
						 	]
						},
					);
			}

			return {
				title : 'Restore previous versions',
				minWidth : 200,
				minHeight : 200,
				contents :
				[
					{
						id : 'general',
						label : 'Settings',
						elements : buttons

					}
				]
			};
		});
// End dialog definition




				editor.openDialog(dialogID);
				//new CKEDITOR.dialogCommand('ckeditorRestoreDialog');
			}
		});

		editor.ui.addButton('restore', { // add new button and bind our command
		    label: "History",
		    command: 'restoreCommand',
		    toolbar: 'document'
		});

		var getRevisionURL = "{{@BASE}}/admin/ckeditor/getRevision";
	}
} );

function makeid()
{
    var text = "";
    var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

    for( var i=0; i < 5; i++ )
        text += possible.charAt(Math.floor(Math.random() * possible.length));

    return text;
}


			// return {
			// 	title : 'Restore previous versions',
			// 	minWidth : 400,
			// 	minHeight : 200,
			// 	contents :
			// 	[
			// 		{
			// 			id : 'general',
			// 			label : 'Settings',
			// 			elements :
			// 			[
			// 				type: "hbox",
			// 				widths: ['50%', '50%'],
			// 				children: [			
			// 				 	{
			// 				 		type: 'button',
			// 				 		id: 'textfieldid',
			// 				 		label: 'Restore (5 minutes ago)'
			// 				 	},
			// 				 	{
			// 				 		type: 'button',
			// 				 		id: 'textfieldid2',
			// 				 		label: 'hello world'
			// 				 	}
			// 				]
			// 			]
			// 		}
			// 	]
			// };