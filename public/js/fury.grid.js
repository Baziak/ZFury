define(['jquery'], function ($) {
    "use strict";
    $(function () {
        initialize();
        $('body').on('form.success', function () {
            $('[data-spy="grid"]').each(function () {
                var $grid = $(this);
                $.ajax({
                    url: $grid.data('url'),
                    type: 'get',
                    dataType: 'html',
                    beforeSend: function () {
                        $grid.find('a, .btn').addClass('disabled');
                    },
                    success: function (html) {
                        $grid.find('a, .btn').removeClass('disabled');
                        $grid.find('.grid').html($(html).find('.grid').children().unwrap());
                    }
                });
                return false;
            });
        });
        $('body').on('grid.reload', function () {
            initialize();
        });

        function initialize() {
            $('[data-spy="grid"]').each(function () {
                var $grid = $(this);
                if (!$grid.data('url')) {
                    $grid.data('url', window.location.pathname);
                }

                $grid.on('click', '.pagination li a, thead a', function () {
                    var $link = $(this),
                        href = $link.attr('href');

                    if (href === '#') {
                        return false;
                    }

                    $.ajax({
                        url: href,
                        type: 'get',
                        dataType: 'html',
                        beforeSend: function () {
                            $link.addClass('active');
                            $grid.find('a, .btn').addClass('disabled');
                        },
                        success: function (html) {
                            $grid.find('a, .btn').removeClass('disabled');
                            $grid.data('url', href);
                            $grid.find('.grid').html($(html).find('.grid').children().unwrap());
                        }
                    });
                    return false;
                }).on('submit', 'form.filter-form', function () {
                    var $form = $(this),
                        $searchInput = $form.find('.grid-filter-search-input');

                    if ($form.find('[type=search]').length) {
                        $searchInput.val($form.find('[type=search]').val());
                    }
                    $.ajax({
                        url: $grid.data('url'),
                        type: 'get',
                        data: $form.serializeArray(),
                        dataType: 'html',
                        beforeSend: function () {
                            $form.addClass('disabled');
                            $grid.find('a, .btn').addClass('disabled');
                        },
                        success: function (html) {
                            $grid.find('a, .btn').removeClass('disabled');
                            $grid.find('.grid').html($(html).find('.grid').children().unwrap());
                        }
                    });

                    return false;
                }).on('click', '.grid-filter-search a', function () {
                    var $a = $(this);
                    $grid.find('.grid-filter-search .dropdown-toggle').html($a.text() + ' <span class="caret"></span>');
                    $grid.find('.grid-filter-search-input').attr('name', 'filter-' + $a.data('filter'));
                });

            });
        }
    });
});