<link href="{{ @BASE }}/admin/css/admin_toolbar.css" rel="stylesheet">
<script src="{{@CONFIG.cdn.ckeditor}}"></script>
<script src="{{@BASE}}/admin/js/admin_toolbar.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>

<script>
	CKEDITOR.disableAutoInline = true;

	<repeat group="{{@ck_instances}}" value="{{@instance}}">

		if (document.getElementById("{{@instance.id}}")) {
			<switch expr="{{@instance.type}}">
				<default>
				var editor = CKEDITOR.inline( '{{@instance.id}}',{ customConfig: '{{ @BASE }}/admin/ckeditor_config.js' } );

				editor.on( 'blur', function( e ) {
					if (e.editor.checkDirty()) {
						$("#savebutton").css("background-color", "red");
					}
				} );

				</default>
			</switch>}
	</repeat>

</script>