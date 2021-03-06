function UsersModule() {
    if (window.username) {
        $('body').on('click', 'button.user_observe_btn', this.observeUser);
        $('body').on('click', 'button.user_block_btn', this.blockUser);

        $('[data-hover=user_widget]').popover({
            html:true, placement:'bottom', trigger: 'hover', delay: 500, content: this.renderActionsWidget
        });
    }
};

UsersModule.prototype.observeUser = function() {
    var button = $(this);
    var name = $(this).parent().attr('data-name');

    if (button.hasClass('btn-success')) {
        $.delete('/u/'+ name +'/observe', function(data){
            if (data.status == 'ok') {
                $(button).removeClass('btn-success');
                $(button).addClass('btn-default');

                window.observed_users = _.without(window.observed_users, name);
            }
        });
    } else {
        $.post('/u/'+ name +'/observe', function(data){
            if (data.status == 'ok') {
                $(button).removeClass('btn-default');
                $(button).addClass('btn-success');

                window.observed_users.push(name);
            }
        });
    }
};

UsersModule.prototype.blockUser = function() {
    var button = $(this);
    var name = $(this).parent().attr('data-name');

    if (button.hasClass('btn-danger')) {
        $.delete('/u/'+ name +'/block', function(data){
            if (data.status == 'ok') {
                $(button).removeClass('btn-danger');
                $(button).addClass('btn-default');

                window.blocked_users = _.without(window.blocked_users, name);
            }
        });
    } else {
        $.post('/u/'+ name +'/block', function(data){
            if (data.status == 'ok') {
                $(button).removeClass('btn-default');
                $(button).addClass('btn-danger');

                window.blocked_users.push(name);
            }
        });
    }
};

UsersModule.prototype.renderActionsWidget = function() {
    var username = $(this).attr('data-user')

    return _.tpl['users-tooltip']({
        username: username,
        observe_class: _.includes(window.observed_users, username) ? 'btn-success' : 'btn-secondary',
        block_class:_.includes(window.blocked_users, username) ? 'btn-danger' : 'btn-secondary'
    });
};

export default UsersModule
