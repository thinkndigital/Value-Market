
'use strict';
(function ($) {

  // Menu toggle for admin dashboard

  if ($("#nav-toggle").length) {
    $("#nav-toggle").on("click", function (e) {
      e.preventDefault();

      var windowHeight = $(window).height();
      var footerHeight = $('.main-footer').outerHeight();
      var headerHeight = $('.header').outerHeight();
      var contentHeight = $('#page-content').outerHeight() - headerHeight - footerHeight;
      $("#db-wrapper").toggleClass("toggled");

      if ($("#db-wrapper").hasClass("toggled")) {

        $('body').addClass('sidebar-mini');

        // If 'toggled' class is present, set left to 0
        if (contentHeight > windowHeight) {
          $('.main-footer').css('left', '0');
        } else {
          $('.main-footer').css('left', '0');
        }

      } else {

        $('body').removeClass('sidebar-mini');

        if (contentHeight > windowHeight) {
          // If 'toggled' class is not present, set left to 250
          $('.main-footer').css('left', '0');
        } else {
          $('.main-footer').css('left', '250px');
        }
      }

    });
  }


  //  slimscroll for sidebar nav

  if ($(".nav-scroller").length) {
    $(".nav-scroller").slimScroll({
      height: "97%",
    });
  }



  // Notification dropdown scroll List

  if ($('.notification-list-scroll').length) {
    $(".notification-list-scroll").slimScroll({
      height: 300,
    });
  }



  // Default Tooltip

  if ($('[data-bs-toggle="tooltip"]').length) {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })
  }


  // Default Popover

  if ($('[data-bs-toggle="popover"]').length) {
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
      return new bootstrap.Popover(popoverTriggerEl)
    })
  }

  // Scrollspy

  if ($('[data-bs-spy="scroll"]').length) {
    var dataSpyList = [].slice.call(document.querySelectorAll('[data-bs-spy="scroll"]'))
    dataSpyList.forEach(function (dataSpyEl) {
      bootstrap.ScrollSpy.getInstance(dataSpyEl)
        .refresh()
    })

  }

  // Toast

  if ($('.toast').length) {

    var toastElList = [].slice.call(document.querySelectorAll('.toast'))
    var toastList = toastElList.map(function (toastEl) {
      return new bootstrap.Toast(toastEl)
    })

  }


  // Perfomance Chart

  if ($("#perfomanceChart").length) {
    var options = {
      series: [100, 78, 89],
      chart: {
        height: 320,
        type: 'radialBar',
      },
      colors: ['#28a745', '#ffc107', '#dc3545'],
      stroke: {
        lineCap: "round",
      },
      plotOptions: {

        radialBar: {
          startAngle: -168,
          endAngle: -450,
          hollow: {

            size: '55%',
          },
          track: {


            background: 'transaprent',
          },
          dataLabels: {
            show: false,

          }
        }
      },

    };

    var chart = new ApexCharts(document.querySelector("#perfomanceChart"), options);
    chart.render();

  }



  // offcanvas
  if ($(".offcanvas").length) {
    var offcanvasElementList = [].slice.call(document.querySelectorAll('.offcanvas'))
    var offcanvasList = offcanvasElementList.map(function (offcanvasEl) {
      return new bootstrap.Offcanvas(offcanvasEl)
    })

  }
})(jQuery);
