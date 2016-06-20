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

  config.removeButtons = 'Source,Save,NewPage,Preview,Print,Templates,Cut,Copy,Paste,PasteText,PasteFromWord,Find,Replace,SelectAll,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Strike,Subscript,Superscript,Outdent,Indent,Blockquote,CreateDiv,BidiLtr,BidiRtl,Language,Anchor,Flash,Table,HorizontalRule,SpecialChar,Smiley,PageBreak,Iframe,Styles,Format,Maximize,ShowBlocks,About';

  config.inlinesave = {
    postUrl: '{{@BASE}}/admin/page/save',
    useJSON: false,
    useColorIcon: true
  };

  config.extraPlugins = 'uploadimage';
  config.removePlugins = 'scayt,menubutton,contextmenu,tabbedimagebrowser';
  config.uploadUrl = '{{@BASE}}/image_upload';
};

// The "instanceCreated" event is fired for every editor instance created.
CKEDITOR.on( 'instanceCreated', function ( event ) {

  var editor = event.editor,
      element = editor.element;
  
      if (element.is('img'))
      {
        editor.on('configLoaded', function () {
          
          editor.config.toolbarGroups = [

          ];

        });
      }

  // Customize editors for headers and tag list.
  // These editors do not need features like smileys, templates, iframes etc.
  if ( element.is( 'h1', 'h2', 'h3' ) || element.getAttribute( 'id' ) == 'taglist' ) {
    // Customize the editor configuration on "configLoaded" event,
    // which is fired after the configuration file loading and
    // execution. This makes it possible to change the
    // configuration before the editor initialization takes place.
    editor.on( 'configLoaded', function () {

      // Remove redundant plugins to make the editor simpler.
      editor.config.removePlugins = 'colorbutton,find,flash,font,' +
          'forms,iframe,image,newpage,removeformat,' +
          'smiley,specialchar,stylescombo,templates';

      // Rearrange the toolbar layout.
      editor.config.toolbarGroups = [
        { name: 'editing', groups: [ 'basicstyles', 'links' ] },
        { name: 'undo' },
        { name: 'colors' },
        { name: 'clipboard', groups: [ 'selection', 'clipboard' ] },
        { name: 'about' }
      ];
    } );
  }

} );