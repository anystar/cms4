CKEDITOR.editorConfig = function( config ) {

  config.removePlugins = 'scayt,uploadimage,Font,ckeditor-gwf-plugin';
  config.toolbar = [ [ 'Image', 'Inlinesave', 'Font' ] ];

  config.inlinesave = {
    postUrl: '{{@BASE}}/admin/page/save',
    useJSON: false,
    useColorIcon: true
  };

  config.uploadUrl = '{{@BASE}}/admin/file_manager/image_upload';

  config.filebrowserBrowseUrl = '{{@BASE}}/admin/file_manager/browse_files';
  config.filebrowserUploadUrl = '{{@BASE}}/admin/file_manager/image_upload_via_dialog';
};