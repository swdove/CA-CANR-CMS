if(window.location.href.indexOf('best_import')!=-1)(function($){

    function expandH3(){
        $('*[id^="tab-"] h3').click(function(e){
            $(this).nextUntil('h3').toggle();
            e.preventDefault();
        }).prepend('&raquo; ');
        
        $('#tab-instruction h3').not('h3:first-of-type').click();
    }
    
    function postTypeChange(){
        $('#bi-type').change(function(){
            $('#bi').submit();
        });
    }
    
    function suggest(){
         $('#bi .suggest').suggest(dir+'/suggest.php', {delay:0, minchars:1});
    }
    
    function updateCustomFields(){
        inputs = $('input[name="custom_names[]"]');
        optgroups = $('optgroup[label="Custom fields"]');
        optgroups.each(function(){
            selected = this.parentNode.value;
            this.innerHTML = '';
            for(var i=0,I=inputs.length; i<I; ++i)
                if(!inputs[i].disabled)
                    $(this).append('<option value="'+inputs[i].value+'" '+(inputs[i].value==selected?'selected="selected"':'')+'>'+inputs[i].value+'</option>');
            ///$(this).append('<option value="" '+(selected==''?'selected="selected"':'')+'>...</option>');
        });
    }
    
    function customFields(){
        $('input[name="custom_names[]"]').live('change', updateCustomFields);
        $('#bi input[name="removeField"]').click(updateCustomFields);
    }
    
    function updateFilteringFields(){
        if(this.value=='update' || this.value=='delete')$(this).nextAll('span').show();
        else $(this).nextAll('span').hide();
    }
    
    function filteringFields(){
        $('select[name="filtering_actions[]"]').live('change', updateFilteringFields).change();
    }
    
    function addField(){
        $('#bi *[id^="bi-add"]').click(function(){
            field = $(this).parent().parent().prev();
            newfield = field.clone(true).show();
            newfield.find('input,select').removeAttr('disabled');
            newfield.find('.suggest').suggest(dir+'/suggest.php', {delay:0, minchars:1});
            field.before(newfield);
        }).parent().parent().prev().hide().find('input,select').attr('disabled','disabled');
    }
        
    function removeField(){
        $('#bi input[name="removeField"]').click(function(e){
            $(this).parent().parent().remove();
            e.preventDefault();
        });
    }
    
     function uploadProgress(){
        bar = $('#bi .bar div');
        if(bar.length)
            $('#bi').attr('action',dir+'/upload-ajax.php').ajaxForm({
                beforeSend: function(){
                    bar.width('0%').html('0%').parent().show();
                    $('#tab-upload h4').first().html('Uploading...').removeClass('h4error');
                },
                uploadProgress: function(event, position, total, percentComplete){
                    bar.width(percentComplete+'%').html(percentComplete+'%');
                },
                success: function(){
                    bar.width('100%').html('100%');
                },
                complete: function(xhr){
                    if(xhr.responseText)$('#tab-upload h4').first().html(xhr.responseText).addClass('h4error');
                    else window.location.reload();
                }
            }); 
    }
    
    function contentTables(){
        $('#bi .tag-content input').click(function(e){
            current = $(this).siblings().filter('span').get(1);
            max = $(this).siblings().filter('span').get(2);
            tables = $(this).parent().next().children();

            n = parseInt(current.innerHTML);
            m = parseInt(max.innerHTML);

            if(this.getAttribute('name')=='next' && n<m)n++;
            else if(this.getAttribute('name')=='prev' && n>1)n--;

            current.innerHTML = n;

            tables.hide();
            $(tables.get(n-1)).show();

            e.preventDefault();
        });
    }
    
    function navigationTabs(){
        $('.nav-tab').click(function(e){
            tab = this.getAttribute('href').slice(1);            
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('[id^="tab-"]').hide();
            $('#tab-'+tab).show();

        }).filter(function(){

            hash = window.location.hash || '#home';
            return this.getAttribute('href')==hash;

        }).click();
    }
    
    $(function(){
        expandH3();
        postTypeChange();
        suggest();
        addField();
        removeField();
        uploadProgress();
        contentTables();
        navigationTabs();  
        customFields();
        filteringFields();
    });
    
})(jQuery);