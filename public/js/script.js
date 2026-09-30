(function($) {

    "use strict";

    var searchPopup = function() {
      // open search box
      $('#header-nav').on('click', '.search-button', function(e) {
        $('.search-popup').toggleClass('is-visible');
      });

      $('#header-nav').on('click', '.btn-close-search', function(e) {
        $('.search-popup').toggleClass('is-visible');
      });
      
      $(".search-popup-trigger").on("click", function(b) {
          b.preventDefault();
          $(".search-popup").addClass("is-visible"),
          setTimeout(function() {
              $(".search-popup").find("#search-popup").focus()
          }, 350)
      }),
      $(".search-popup").on("click", function(b) {
          ($(b.target).is(".search-popup-close") || $(b.target).is(".search-popup-close svg") || $(b.target).is(".search-popup-close path") || $(b.target).is(".search-popup")) && (b.preventDefault(),
          $(this).removeClass("is-visible"))
      }),
      $(document).keyup(function(b) {
          "27" === b.which && $(".search-popup").removeClass("is-visible")
      })
    }

    var initProductQty = function(){

      $('.product-qty').each(function(){

        var $el_product = $(this);
        var quantity = 0;

        $el_product.find('.quantity-right-plus').click(function(e){
            e.preventDefault();
            var quantity = parseInt($el_product.find('#quantity').val());
            $el_product.find('#quantity').val(quantity + 1);
        });

        $el_product.find('.quantity-left-minus').click(function(e){
            e.preventDefault();
            var quantity = parseInt($el_product.find('#quantity').val());
            if(quantity>0){
              $el_product.find('#quantity').val(quantity - 1);
            }
        });

      });

    }

    var cartAnimation = function() {
      $(document).on('submit', 'form', function(e) {
        var $form = $(this);
        var action = $form.attr('action');

        // Ne s'applique qu'aux formulaires d'ajout au panier
        if (!action || action.indexOf('/cart/add') === -1) {
            return;
        }

        var $btn = $form.find('button[type="submit"]');
        if ($btn.prop('disabled')) {
            return;
        }

        var $container = $form.closest('.ms-tile, .ms-product__grid');
        var $image = $container.find('img').first();
        var $cartIcon = $('.ms-action[href*="/cart"]').first();

        if ($image.length > 0 && $cartIcon.length > 0) {
            e.preventDefault();
            $btn.prop('disabled', true);

            var imageOffset = $image.offset();
            var cartOffset = $cartIcon.offset();

            var $clone = $image.clone()
                .offset({
                    top: imageOffset.top,
                    left: imageOffset.left
                })
                .css({
                    'opacity': '0.9',
                    'position': 'absolute',
                    'height': $image.height(),
                    'width': $image.width(),
                    'z-index': '999999',
                    'border-radius': '50%',
                    'object-fit': 'cover',
                    'box-shadow': '0px 10px 30px rgba(0,0,0,0.5)'
                })
                .appendTo($('body'));

            $clone.animate({
                'top': cartOffset.top,
                'left': cartOffset.left,
                'width': 25,
                'height': 25,
                'opacity': 0.1
            }, 800, 'swing', function() {
                $clone.remove();
                $cartIcon.css('transform', 'scale(1.2)');
                setTimeout(function(){ $cartIcon.css('transform', 'scale(1)'); }, 200);
            });

            $.ajax({
                url: $form.attr('action'),
                method: $form.attr('method') || 'POST',
                data: $form.serialize(),
                success: function(data) {
                    var $parsed = $($.parseHTML(data));
                    var newCartHtml = $parsed.find('.ms-action[href*="/cart"]').first().html();
                    if (newCartHtml) {
                        $('.ms-action[href*="/cart"]').first().html(newCartHtml);
                    }
                    $btn.prop('disabled', false);
                },
                error: function() {
                    $form.off('submit')[0].submit();
                }
            });
        }
      });
    };

    $(document).ready(function() {

      searchPopup();
      initProductQty();
      cartAnimation();

      var swiper = new Swiper(".main-swiper", {
        speed: 500,
        navigation: {
          nextEl: ".swiper-arrow-prev",
          prevEl: ".swiper-arrow-next",
        },
      });         

      var swiper = new Swiper(".product-swiper", {
        slidesPerView: 4,
        spaceBetween: 10,
        pagination: {
          el: "#mobile-products .swiper-pagination",
          clickable: true,
        },
        breakpoints: {
          0: {
            slidesPerView: 2,
            spaceBetween: 20,
          },
          980: {
            slidesPerView: 4,
            spaceBetween: 20,
          }
        },
      });      

      var swiper = new Swiper(".product-watch-swiper", {
        slidesPerView: 4,
        spaceBetween: 10,
        pagination: {
          el: "#smart-watches .swiper-pagination",
          clickable: true,
        },
        breakpoints: {
          0: {
            slidesPerView: 2,
            spaceBetween: 20,
          },
          980: {
            slidesPerView: 4,
            spaceBetween: 20,
          }
        },
      }); 

      var swiper = new Swiper(".testimonial-swiper", {
        loop: true,
        navigation: {
          nextEl: ".swiper-arrow-prev",
          prevEl: ".swiper-arrow-next",
        },
      }); 

    }); // End of a document ready

})(jQuery);