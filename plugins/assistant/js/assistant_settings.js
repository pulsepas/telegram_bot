/**
 * Created by snark on 9/28/15.
 */

(function ($) {
    $.Assistant_settings = {
        init: function() {
            var self = this;
            self.initTabs();
        },
        initTabs: function () {
            var self = this, tabs = $("#assistant-tabs").children(), cnt = $("#assistant-tabs-content").children();
            cnt.hide().first().show();
            tabs.click(function (e) {
                if (!$(this).hasClass('selected')) {
                    $(this).addClass("selected").siblings().removeClass("selected");
                    var tab = $("a", this).attr("href");
                    $(tab).fadeToggle().siblings().hide();
                }
                return false;
            });
        }
    }
})(jQuery);



