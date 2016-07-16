CKEDITOR.editorConfig = function( config ) {
  config.toolbarGroups = [
    { name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
    { name: 'forms', groups: [ 'forms' ] },
    { name: 'links', groups: [ 'links' ] },
    { name: 'insert', groups: [ 'insert' ] },
    { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align', 'bidi', 'paragraph' ] },
    { name: 'editing', groups: [ 'find', 'selection', 'spellchecker', 'editing', 'others', 'tools' ] },
    '/',
    { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
    { name: 'styles', groups: [ 'styles' ] },
    { name: 'colors', groups: [ 'colors' ] },
    { name: 'document', groups: [ 'mode', 'document', 'doctools' ] },
  ];

  config.removeButtons = 'Source,Save,NewPage,Preview,Print,Templates,Cut,Copy,Paste,PasteText,PasteFromWord,Find,Replace,SelectAll,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Strike,Subscript,Superscript,Outdent,Indent,Blockquote,CreateDiv,BidiLtr,BidiRtl,Language,Anchor,Flash,HorizontalRule,SpecialChar,Smiley,PageBreak,Iframe,Styles,Format,Maximize,ShowBlocks,About';

  config.inlinesave = {
    postUrl: '{{@BASE}}/admin/page/save',
    useJSON: false,
    useColorIcon: true
  };

  config.extraPlugins = 'uploadimage,ckeditor-gwf-plugin';
  config.removePlugins = 'scayt';
  config.uploadUrl = '{{@BASE}}/admin/file_manager/image_upload';

  config.filebrowserBrowseUrl = '{{@BASE}}/admin/file_manager/browse_files';
  config.filebrowserUploadUrl = '{{@BASE}}/admin/file_manager/image_upload_via_dialog';

  config.font_names = config.font_names + ";GoogleWebFonts";
};

/*CKEDITOR.on( 'dialogDefinition', function( ev )
  {
    // Take the dialog name and its definition from the event data.
    var dialogName = ev.data.name;
    var dialogDefinition = ev.data.definition;
 
    // Check if the definition is from the dialog window you are interested in (the "Link" dialog window).
    if ( dialogName == 'image' )
    {
      // Get a reference to the "Link Info" tab.
      var infoTab = dialogDefinition.getContents( 'info' );
 
      // Set the default value for the URL field.
      var urlField = infoTab.get( 'url' );
      urlField['default'] = 'www.google.com';
    }
  });*/