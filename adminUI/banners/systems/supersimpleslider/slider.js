jQuery(document).ready(function ($) {
    var slider = $('#slider ul li');

    <check if="{{@banner.autoplay==1}}">
    setInterval(function () {
        moveRight();
    }, {{@banner.speed}});
    </check>
    

	$('#slider').css({ height: calc().slideHeight });
	
	$('#slider ul').css({ width: calc().sliderUlWidth, marginLeft: - calc().slideWidth });
	
    $('#slider ul li:last-child').prependTo('#slider ul');

    function calc()
    {
        return {
            slideCount: $(slider).length,
            slideWidth: $(slider).width(),
            slideHeight: $(slider).height(),
            sliderUlWidth: $(slider).length * $(slider).width()
        }
    }

    function moveLeft() {
        $('#slider ul').animate({
            left: + calc().slideWidth
        }, 700, function () {
            $('#slider ul li:last-child').prependTo('#slider ul');
            $('#slider ul').css('left', '');
        });
    };

    function moveRight() {
        $('#slider ul').animate({
            left: - calc().slideWidth
        }, 700, function () {
            $('#slider ul li:first-child').appendTo('#slider ul');
            $('#slider ul').css('left', '');
        });
    };

    $('a.control_prev').click(function () {
        moveLeft();
        return false;
    });

    $('a.control_next').click(function () {
        moveRight();
        return false;
    });

});    
