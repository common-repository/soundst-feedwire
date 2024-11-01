(function($){
    $.fn.extend({ 
        MyPagination: function(options) {
            var defaults = {
                counter: 10,
                fadeSpeed: 400
            };
            var options = $.extend(defaults, options);

            //Creating a reference to the object
            var objContent = $(this);

            // other inner variables
            var fullPages = new Array();
            var subPages = new Array();
            var height = 0;
            var lastPage = 1;
            var paginatePages;
            var maxWidth = 0;
            var maximums = new Array();
            // initialization function
            init = function() {
                objContent.children().each(function(i){
                    if (((i % options.counter) == 0) && (i > 0)) {
                    	//console.log(i);
                        fullPages.push(subPages);
                        subPages = new Array();
                        maximums.push(maxWidth);
                        maxWidth = 0;
                    }

                    if ($(this).find('.fw_headline').width() > maxWidth)
                    	maxWidth = $(this).find('.fw_headline').width();
                   // console.log($(this).find('.fw_headline').width());
                    
                    height += this.clientHeight;
                    subPages.push(this);
                });

                if (height > 0) {
                    fullPages.push(subPages);
                    maximums.push(maxWidth);
                    maxWidth = 0;
                }
                
            //    console.log(maximums);
                // wrapping each full page
                $(fullPages).wrap("<div class='page'></div>");
                
                // hiding all wrapped pages
                if(1 < fullPages.length)
                objContent.children().hide();

                // making collection of pages for pagination
                paginatePages = objContent.children();

                // show first page
                showPage(lastPage);

            };

            // update counter function
            updateCounter = function(i) {
                $('#page_number').html(i);
                $('.pagination').css({"max-width":maximums[i-1]});
                if(i == 1)
                	$('.pagination #prev').css({"display":"none"});
                else
                	$('.pagination #prev').css({"display":"inline"});
                if(i == fullPages.length)
                	$('.pagination #next').css({"display":"none"});
                else
                	$('.pagination #next').css({"display":"inline"});
            };

            // show page function
            showPage = function(page) {
                i = page - 1; 
                if (paginatePages[i]) {

                    // hiding old page, display new one
                    $(paginatePages[lastPage]).fadeOut(options.fadeSpeed).queue(function(){
                    	lastPage = i;
                        $(paginatePages[lastPage]).fadeIn(options.fadeSpeed);
                        $(this).dequeue();
                    });
                    

                    // and updating counter
                    updateCounter(page);
                }
            };

            // perform initialization
            init();

            // and binding 2 events - on clicking to Prev
            $('.pagination #prev').click(function() {
                showPage(lastPage);
            });
            // and Next
            $('.pagination #next').click(function() {
                showPage(lastPage+2);
            });

        }
    });
})(jQuery);

//custom initialization
//jQuery(window).load(function() {
  //  $('.fw_inside').MyPagination({counter: 2, fadeSpeed: 400});
//});