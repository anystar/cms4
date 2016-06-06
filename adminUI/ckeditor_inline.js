<script>
	CKEDITOR.disableAutoInline = true;

	<repeat group="{{@ck_instances}}" value="{{@instance}}">
	if (document.getElementById("{{@instance}}")) {
	CKEDITOR.inline( '{{@instance}}', { customConfig: '{{ @BASE }}/admin/ckeditor_config.js' } );
	}
	</repeat>
</script>