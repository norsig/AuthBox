
$(function () {

    $("[data-toggle='popover']").popover({
        html: true,
        container: "body"
    });

    $("[data-toggle='tooltip']").tooltip({
        container: "body"
    });

    $("[data-input-select]").click(function(e) {
        $(this).select();
    });

    $("[data-action='delete-user']").click(function(e) {
        e.preventDefault();

        var el = $(this),
            modal = $(el.data("modal-target"));

        modal.find("[data-modal-action='delete-user']")
            .attr("href", el.attr("href"));

        modal.modal();
    });

    $("[data-action='edit-user']").click(function(e) {
        e.preventDefault();

        var el = $(this),
            modal = $(el.data("modal-target")),
            userId = el.data("user-id");

        if (!__users.hasOwnProperty(userId)) {
            return;
        }

        var user = __users[userId];
        for (var key in user) {
            var value = user[key],
                input = modal.find("#editUser-" + key);

            if (!input.length) {
                continue;
            }

            var type = input.attr("type"),
                isCheck = type === "checkbox" || type === "radio";

            if (isCheck) {
                input.attr("checked", value);
            } else {
                input.val(value);
            }
        }

        modal.find("form").attr("action", el.attr("href"));

        modal.modal();
    });

});
