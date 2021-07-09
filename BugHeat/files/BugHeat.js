
var BugHeatJs = function() {
    var callAffectAction = function($item) {
        var affected = parseInt($('#BugHeat--affectMessage').attr('data-affected'));
        if(affected === 1) {
            removeAffect($item);
        } else {
            addAffect($item);
        }
    },
    addAffect = function($item){
        $.ajax({ 
            url: config['short_path']+"api/rest/bugheat/addaffect", 
            method: 'POST', 
            data: { 'bugid': parseInt($('td.bug-id').html()) },
            success: function(resolve){
                if(resolve.success) {
                    $('#BugHeat--affectMessage').text(resolve.newString);
                    $('#BugHeat--affectMessage').attr('data-affected', 1);
                    $('#BugHeat--badge-count').text(resolve.newHeat);
                    $('#BugHeat--badge')
                        .removeClass('badge-danger')
                        .removeClass('badge-warning')
                        .removeClass('badge-info')
                        .removeClass('badge-default')
                        .addClass(getNewBadgeClass(resolve.newHeat));
                    
                }
            }
        })
    },
    removeAffect = function($item){
        $.ajax({ 
            url: config['short_path']+"api/rest/bugheat/removeaffect", 
            method: 'POST', 
            data: { 'bugid': parseInt($('td.bug-id').html()) },
            success: function(resolve){
                if(resolve.success) {
                    $('#BugHeat--affectMessage').text(resolve.newString);
                    $('#BugHeat--affectMessage').attr('data-affected', 0);
                    $('#BugHeat--badge-count').text(resolve.newHeat);
                    $('#BugHeat--badge')
                        .removeClass('badge-danger')
                        .removeClass('badge-warning')
                        .removeClass('badge-info')
                        .removeClass('badge-default')
                        .addClass(getNewBadgeClass(resolve.newHeat));
                }
            }
        })
    },
    getNewBadgeClass = function(newHeat){
        return (
            newHeat > 100
            ? 'badge-danger'
            : (
                newHeat > 20
                ? 'badge-warning'
                    : (newHeat > 1 ? 'badge-info' : 'badge-default')
                )
        );
    }
    wireTriggers = function(){
        $('#BugHeat--action-link-affection').on('click', function(e){e.preventDefault(); callAffectAction($(this))});
        $('#BugHeat--modal-link-addaffect').on('click', function(e){e.preventDefault(); addAffect($(this))});
    }

    wireTriggers();

}




$(document).on('ready', function(){
    //removing the affect ->
    BugHeatJs();
    $('[data-toggle="popover"]').popover()
    
})