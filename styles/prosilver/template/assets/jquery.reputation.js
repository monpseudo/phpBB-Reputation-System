/**
*
* @package Reputation System
* @copyright (c) 2013 Pico88
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

$(document).ready(function() {
	/*var arr = [1, 3];
	$.each(arr, function() {
		$("#p" + this).addClass( "highlight" );
	});*/

	// Działa :)
	/*
	$('.positive').each(function() {
		$(this).parents('.post').addClass("hidden");
	});*/
});

$('body').click(function(){
	if (!requestSent)
	{
		$("#reputation-popup").fadeOut('fast');
	}
});

$('#reputation-popup').click(function(e){
	e.stopPropagation();
});

$('#rate-user').click(function(event){
	show_popup(this.href, event, 'user');
});

$('.rate-good-icon a').click(function(event){
	show_popup(this.href, event, 'post');
});

$('.rate-bad-icon a').click(function(event){
	show_popup(this.href, event, 'post');
});

$('.post-reputation a').click(function(event){
	show_popup(this.href, event, 'details');
});

$('.user-reputation a').click(function(event){
	show_popup(this.href, event, 'details');
});

// Save vote
$('#reputation-popup').on("click", '.button1', function(event){
	event.stopPropagation();
	event.preventDefault();

	submit_action($('#reputation-popup > form').attr('action'), 'post');
});

// Cancel rating
$('#reputation-popup').on("click", '.button2', function(event){
	event.stopPropagation();
	event.preventDefault();

	$('#reputation-popup').fadeOut('fast').queue(function () {
		$(this).empty();
		$(this).dequeue();
	});
});

// Sort reputation by
$('#reputation-popup').on("click", 'a.sort_order', function(event){
	event.stopPropagation();
	event.preventDefault();

	sort_order_by(this.href)
});

// Delete reputation
$('#reputation-popup').on("click", '.reputation-delete', function(event){
	event.stopPropagation();
	event.preventDefault();

	var confirmation = $('a.reputation-delete').attr('data-lang-confirm');

	if (confirm(confirmation))
	{
		submit_action(this.href, 'delete')
	}
});

// Clear reputation
$('#reputation-popup').on("click", '.clear-reputation', function(event){
	event.stopPropagation();
	event.preventDefault();

	var confirmation = $('a.clear-reputation').attr('data-lang-confirm');

	if (confirm(confirmation))
	{
		submit_action(this.href, 'clear')
	}
});

/**
* Show the repuation popup with proper data
*/
function show_popup(href, event, mode)
{
	event.stopPropagation();
	event.preventDefault();

	if (!requestSent)
	{
		requestSent = true;

		$.ajax({
			url: href,
			dataType: 'html',
			beforeSend: function() {
				$('#reputation-popup').hide().empty().removeClass('small-popup normal-popup');
			},
			success: function(data) {
				// Fix - do not display the empty popup when comment and reputation power are disabled
				if (data.substr(0,1) != '{')
				{
					$('#reputation-popup').append(data).fadeIn('fast');
				}

				switch(mode)
				{
					case 'details':
						$('#reputation-popup').addClass('normal-popup');
						targetleft = ($(window).width() - $('#reputation-popup').outerWidth()) / 2;
						targettop = ($(window).height() - $('#reputation-popup').outerHeight()) / 2;
					break;
					default:
						$('#reputation-popup').addClass('small-popup');
						// Center popup relative to clicked coordinate
						targetleft = event.pageX - $('#reputation-popup').outerWidth() / 2;
						// Popup can not be too close or behind the right border of the screen
						targetleft = Math.min (targetleft, $(document).width() - 20 - $('#reputation-popup').outerWidth());
						targetleft = Math.max (targetleft, 20);
						targettop = event.pageY + 10;
					break;
				}

				$('#reputation-popup').css({'top': targettop + 'px', 'left': targetleft + 'px'});

				if (data.substr(0,1) == '{')
				{
					// It's JSON! Probably an error. Lets clean the reputation popup and show the error there
					response(jQuery.parseJSON(data), mode);
				}
			},
			complete: function() {
				setTimeout('request_sent()', 750);
			}
		});
	}
}

/**
* Function which allow to sent request after popup time out
*/
function request_sent()
{
	requestSent = false;
}

/**
* Submit reputation action
*/
function submit_action(href, mode)
{
	switch(mode)
	{
		case 'post':
		case 'user':
			data = $('#reputation-popup form').serialize();
		break;
		default:
			data = '';
		break;
	}

	$.ajax({
		url: href,
		data: data,
		dataType: 'json',
		type: 'POST',
		success: function(r) {
			response(r, mode);
		}
	});
}

/** 
* Reputation response
*/
function response(data, mode)
{
	if (data.error_msg)
	{
		// If there is an error, show it
		$('#reputation-popup').empty().append('<div class="error">' + data.error_msg + '</div>').fadeIn();
	}
	else if (data.comment_error)
	{
		// If there is a comment error, show it
		$('.error').detach();
		$('.comment').append('<dl class="error"><span>' + data.comment_error + '</span></dl>');
	}
	else
	{
		// Otherwise modify the board outlook
		switch (mode)
		{
			case 'post':
				var post_id = data.post_id;
				var poster_id = data.poster_id;

				$('#reputation-popup').empty().append('<div class="error">' + data.success_msg + '</div>').delay(800).fadeOut('fast').queue(function() {
					$(this).empty();
					$('#profile' + poster_id + ' a').html(data.user_reputation);
					$('#p' + post_id + ' .post-reputation a').text(data.post_reputation);
					$('#p' + post_id + ' .post-reputation').removeClass('neutral negative positive').addClass(data.reputation_class);
					$('#p' + post_id + ' .rate-good-icon').removeClass('rated_good rated_bad').addClass(data.reputation_vote);
					$('#p' + post_id + ' .rate-bad-icon').removeClass('rated_good rated_bad').addClass(data.reputation_vote);
					$(this).dequeue();
				});
			break;

			case 'user':
				$('#reputation-popup').empty().append('<div class="error">' + data.success_msg + '</div>').delay(800).fadeOut('fast').queue(function() {
					$(this).empty();
					$('#user-reputation').html(data.user_reputation);
					$('#rate-user').html(data.user_reputation);
					$(this).dequeue();
				});
			break;

			case 'delete':
				var post_id = data.post_id;
				var poster_id = data.poster_id;
				var rep_id = data.rep_id;

				$('#r' + rep_id).hide('fast', function() {
					$('#r' + rep_id).detach();
					if ($('.reputation-list').length == 0)
					{
						$('#reputation-popup').fadeOut('fast').empty();
					}
				});
				$('#profile' + poster_id + ' a').html(data.user_reputation);
				$('#p' + post_id + ' .post-reputation a').text(data.post_reputation);
				$('#p' + post_id + ' .post-reputation').removeClass('zero negative positive').addClass(data.reputation_class);

				if (data.own_vote)
				{
					$('#p' + post_id + ' .rate-good-icon').removeClass('rated_good rated_bad');
					$('#p' + post_id + ' .rate-bad-icon').removeClass('rated_good rated_bad');
				}
			break;

			case 'clear':
				if (data.clear_post)
				{
					var post_id = data.post_id;
					var poster_id = data.poster_id;

					$('.reputation-list').slideUp(function() {
						$('#reputation-popup').fadeOut('fast').empty();
					});
					$('#profile' + poster_id + ' a').html(data.user_reputation);
					$('#p' + post_id + ' .post-reputation a').text(data.post_reputation);
					$('#p' + post_id + ' .post-reputation').removeClass('neutral negative positive').addClass(data.reputation_class);
					$('#p' + post_id + ' .rate-good-icon').removeClass('rated_good rated_bad');
					$('#p' + post_id + ' .rate-bad-icon').removeClass('rated_good rated_bad');
				}
				else if (data.clear_user)
				{
					var post_ids = data.post_ids;
					var poster_id = data.poster_id;

					$('.reputation-list').slideUp(function() {
						$('#reputation-popup').fadeOut('fast').empty();
					});
					$('#profile' + poster_id + ' a').html(data.user_reputation);

					$.each(post_ids, function(i, post_id) { 
						$('#p' + post_id + ' .post-reputation a').text(data.post_reputation);
						$('#p' + post_id + ' .post-reputation a').text(data.post_reputation);
						$('#p' + post_id + ' .post-reputation').removeClass('neutral negative positive').addClass(data.reputation_class);
						$('#p' + post_id + ' .rate-good-icon').removeClass('rated_good rated_bad');
						$('#p' + post_id + ' .rate-bad-icon').removeClass('rated_good rated_bad');
					});
				}
			break;
		}
	}
}

/**
* Sort reputations
*/
function sort_order_by(href)
{
	$.ajax({
		url: href,
		dataType: 'html',
		success: function(s) {
			$('#reputation-popup').empty().append(s);
		}
	});
}