// to get current year
function getYear() {
    var currentDate = new Date();
    var currentYear = currentDate.getFullYear();
    document.querySelector("#displayYear").innerHTML = currentYear;
}

getYear();

console.log('[BizHub] custom.js loaded');


// isotope js
$(window).on('load', function () {
    $('.filters_menu li').click(function () {
        $('.filters_menu li').removeClass('active');
        $(this).addClass('active');

        var data = $(this).attr('data-filter');
        $grid.isotope({
            filter: data
        })
    });

    var $grid = $(".grid").isotope({
        itemSelector: ".all",
        percentPosition: false,
        masonry: {
            columnWidth: ".all"
        }
    })
});

// nice select
$(document).ready(function() {
    $('select').niceSelect();
  });

/** google_map js **/
function myMap() {
    var mapEl = document.getElementById("googleMap");
    if (!mapEl || typeof google === 'undefined' || !google.maps) {
        return;
    }
    var mapProp = {
        center: new google.maps.LatLng(40.712775, -74.005973),
        zoom: 18,
    };
    var map = new google.maps.Map(mapEl, mapProp);
}

// client section carousel
$(document).ready(function() {
    var $testimonialsCarousel = $('#customCarousel2');
    if ($testimonialsCarousel.length && typeof $testimonialsCarousel.carousel === 'function') {
        console.log('[BizHub] binding testimonials carousel');
        $testimonialsCarousel.carousel({
            interval: 5000,
            wrap: true,
            pause: "hover"
        });

        $('#testimonialsPrev').on('click', function(e) {
            e.preventDefault();
            console.log('[BizHub] testimonials prev');
            $testimonialsCarousel.carousel('prev');
        });

        $('#testimonialsNext').on('click', function(e) {
            e.preventDefault();
            console.log('[BizHub] testimonials next');
            $testimonialsCarousel.carousel('next');
        });
    }
});