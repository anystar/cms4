CKEDITOR.editorConfig = function( config ) {
	config.extraPlugins = 'ckeditor-gwf-plugin';
	config.removePlugins = 'scayt,uploadimage';
  	
  	config.toolbar = [ [ 'Font', 'FontSize', 'TextColor', 'Inlinesave' ] ];


	config.inlinesave = {
		postUrl: '{{@BASE}}/admin/page/save',
		useJSON: false,
		useColorIcon: true
	};
	config.font_names = "GoogleWebFonts;" + config.font_names;
};