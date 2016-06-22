CKEDITOR.editorConfig = function( config ) {

  config.toolbar = [ [ 'Image', 'Inlinesave' ] ];

  config.inlinesave = {
    postUrl: '{{@BASE}}/admin/page/save',
    useJSON: false,
    useColorIcon: true
  };

  config.removePlugins = 'scayt,uploadimage';
  config.uploadUrl = '{{@BASE}}/admin/file_manager/image_upload';

  config.filebrowserBrowseUrl = '{{@BASE}}/admin/file_manager/browse_files';
  config.filebrowserUploadUrl = '{{@BASE}}/admin/file_manager/image_upload_via_dialog';
};