<link href="{{ @BASE }}/admin/css/admin_toolbar.css" rel="stylesheet">
<script src="{{@CONFIG.cdn.ckeditor}}"></script>
<script src="{{@BASE}}/admin/js/admin_toolbar.js"></script>

<script>
	CKEDITOR.disableAutoInline = true;

	<repeat group="{{@ck_instances}}" value="{{@instance}}">

		if (document.getElementById("{{@instance.id}}")) {
			<switch expr="{{@instance.type}}">
				<default>var editor = CKEDITOR.inline( '{{@instance.id}}',{ customConfig: '{{ @BASE }}/admin/ckeditor_config.js' } );</default>
			</switch>}
	</repeat>

	editor.on('blur', function () {
		console.log("test");
	});
</script>